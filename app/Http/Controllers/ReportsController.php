<?php

namespace App\Http\Controllers;

use App\Support\Reports\ActivityReport;
use App\Support\Reports\OpportunityReport;
use App\Support\Reports\ProspectReport;
use App\Support\Reports\QuoteReport;
use App\Support\Reports\ReportFilters;
use App\Support\SelectOptions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reportes comerciales de solo lectura.
 *
 * No guarda nada: cada visita agrega sobre las mismas tablas que alimentan los
 * listados, así que los números se pueden auditar contra el listado con el
 * mismo filtro.
 *
 * Dos reglas gobiernan la página. La primera es la autorización en dos capas:
 * reports.view decide la entrada en la ruta y, dentro, cada bloque se arma solo
 * si el usuario puede consultar el módulo que agrega (null si no, como en el
 * dashboard), de modo que nunca se muestra más de lo que muestra el listado.
 *
 * La segunda es que el periodo lo resuelve el backend y compara por día, para
 * que "del 1 al 31" incluya ambas fechas enteras y no se recorte el último día.
 */
class ReportsController extends Controller
{
    public function __construct(
        private readonly OpportunityReport $opportunities,
        private readonly QuoteReport $quotes,
        private readonly ProspectReport $prospects,
        private readonly ActivityReport $activities,
    ) {}

    /**
     * Los tres filtros de la pantalla: periodo y responsable.
     *
     * El responsable se valida contra la tabla de usuarios y no contra los
     * activos, porque hay que poder consultar el trabajo de una cuenta que fue
     * desactivada después de vender en el periodo. El desplegable sí ofrece
     * solo activos, como hace SelectOptions en el resto de los módulos.
     *
     * after_or_equal solo se declara cuando hay fecha inicial: con un filtro
     * que solo trae "hasta" no hay nada contra qué comparar.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::when($request->filled('date_from'), 'after_or_equal:date_from'),
            ],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ], [
            'date_from.date_format' => __('reports.invalid_date'),
            'date_to.date_format' => __('reports.invalid_date'),
            'date_to.after_or_equal' => __('reports.invalid_range'),
        ]);

        $filters = ReportFilters::from($validated);
        $user = $request->user();

        // Un solo cálculo por bloque, reutilizado en todas las props que lo
        // necesiten: repetirlo volvería a correr las consultas del periodo.
        $opportunities = $user->can('opportunities.view') ? $this->opportunities : null;
        $quotes = $user->can('quotes.view') ? $this->quotes : null;
        $prospects = $user->can('prospects.view') ? $this->prospects : null;
        $activities = $user->can('activities.view') ? $this->activities->summarize($filters) : null;

        return Inertia::render('Reports/Index', [
            // Null cuando falta el permiso de consulta del módulo, con el mismo
            // criterio que el dashboard: sin dato ni contador que lo delate.
            'opportunities' => $opportunities?->summary($filters),
            'quotes' => $quotes?->summary($filters),
            'prospects' => $prospects?->summary($filters),
            'activities' => $activities,

            // Las etapas van en su propio prop porque son de un solo módulo.
            // Los otros ejes se agrupan por eje y no por módulo porque los
            // comparten varios bloques: "estado" lo usan cotizaciones y
            // prospectos, "responsable" los tres.
            'byStage' => $opportunities?->byStage($filters),
            'byStatus' => [
                'quotes' => $quotes?->byStatus($filters),
                'prospects' => $prospects?->byStatus($filters),
            ],
            'byResponsible' => [
                'opportunities' => $opportunities?->byResponsible($filters),
                'prospects' => $prospects?->byResponsible($filters),
                'activities' => $activities['byResponsible'] ?? null,
            ],

            // El periodo se devuelve tal como se recibió, ya validado.
            'filters' => $validated,

            'users' => SelectOptions::users(),

            // Qué se puede consultar aquí, para los enlaces a los listados.
            'can' => [
                'opportunities' => $opportunities !== null,
                'quotes' => $quotes !== null,
                'prospects' => $prospects !== null,
                'activities' => $activities !== null,
            ],
        ]);
    }
}
