<?php

namespace App\Support\Reports;

use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reporte de oportunidades: embudo y rendimiento por responsable.
 *
 * El periodo lo delimita "created_at" porque la tabla no guarda fecha de
 * cierre. Hay una "expected_close_date", pero es lo que el vendedor estima, no
 * cuándo se ganó o se perdió, y usarla daría números que no significan nada.
 * Agregar una columna "closed_at" sería lo correcto con pronósticos, y el
 * alcance lo prohíbe.
 *
 * Responde: de las oportunidades CREADAS en el periodo, cuántas siguen
 * abiertas, cuántas ganadas y cuántas perdidas hoy. Reproducible y auditable
 * contra el listado con el mismo filtro de fecha.
 *
 * Todo sale de la base de datos: un count y sumas condicionales en la misma
 * consulta, más una agrupación por etapa y otra por responsable. Las claves de
 * ganada y perdida viven en config/opportunities.php, como en el resto del
 * sistema, para que un estado nuevo no deje este reporte contando otra cosa.
 */
final class OpportunityReport
{
    /**
     * Columna que delimita el periodo. Ver la nota de la clase: no es la fecha
     * de cierre.
     */
    private const DATE_COLUMN = 'created_at';

    /**
     * Conteos y montos del periodo completo.
     *
     * Un solo count con sumas condicionales en lugar de seis consultas, porque
     * las seis medidas del encabezado tienen que cuadrar entre sí.
     *
     * "amount" es nullable, así que el sum puede dar null sin filas que la
     * cumplan; los dos ceros se formatean a dos decimales como en
     * App\Http\Controllers\DashboardController.
     *
     * @return array{
     *     total: int,
     *     open: int,
     *     won: int,
     *     lost: int,
     *     openAmount: string,
     *     wonAmount: string
     * }
     */
    public function summary(ReportFilters $filters): array
    {
        $open = (string) config('opportunities.open_status');
        $won = $this->closingStatus('ganada');
        $lost = $this->closingStatus('perdida');

        $row = $this->query($filters)
            ->selectRaw('count(*) as total')
            ->selectRaw('coalesce(sum(case when status = ? then 1 else 0 end), 0) as open_count', [$open])
            ->selectRaw('coalesce(sum(case when status = ? then 1 else 0 end), 0) as won_count', [$won])
            ->selectRaw('coalesce(sum(case when status = ? then 1 else 0 end), 0) as lost_count', [$lost])
            ->selectRaw('coalesce(sum(case when status = ? then amount else 0 end), 0) as open_amount', [$open])
            ->selectRaw('coalesce(sum(case when status = ? then amount else 0 end), 0) as won_amount', [$won])
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'open' => (int) ($row->open_count ?? 0),
            'won' => (int) ($row->won_count ?? 0),
            'lost' => (int) ($row->lost_count ?? 0),
            'openAmount' => $this->money($row->open_amount ?? 0),
            'wonAmount' => $this->money($row->won_amount ?? 0),
        ];
    }

    /**
     * Distribución del periodo por etapa.
     *
     * Se recorre el catálogo completo de config/opportunities.php y no solo
     * las etapas abiertas: el embudo tiene que mostrar dónde se atascan las
     * operaciones. Al recorrer el catálogo y no la consulta, una etapa vacía
     * aparece con cero en lugar de desaparecer.
     *
     * @return list<array{stage: string, label: string, count: int}>
     */
    public function byStage(ReportFilters $filters): array
    {
        $counts = $this->query($filters)
            ->selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $stages = config('opportunities.stages');

        $rows = [];

        foreach (array_keys($stages) as $stage) {
            $rows[] = [
                'stage' => $stage,
                'label' => $stages[$stage],
                'count' => (int) ($counts[$stage] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * El periodo agrupado por responsable.
     *
     * La misma medida del resumen en una sola consulta agrupada. Solo aparece
     * quien tuvo operaciones en el periodo; las filas sin responsable se
     * listan con la etiqueta de lang/es/reports.php, porque eliminar una
     * cuenta no borra el historial y callarlo haría que el total no cuadre.
     *
     * @return list<array{
     *     user_id: int|null,
     *     name: string,
     *     total: int,
     *     open: int,
     *     won: int,
     *     lost: int,
     *     openAmount: string,
     *     wonAmount: string
     * }>
     */
    public function byResponsible(ReportFilters $filters): array
    {
        $open = (string) config('opportunities.open_status');
        $won = $this->closingStatus('ganada');
        $lost = $this->closingStatus('perdida');

        $rows = $this->query($filters)
            ->selectRaw('user_id')
            ->selectRaw('count(*) as total')
            ->selectRaw('coalesce(sum(case when status = ? then 1 else 0 end), 0) as open_count', [$open])
            ->selectRaw('coalesce(sum(case when status = ? then 1 else 0 end), 0) as won_count', [$won])
            ->selectRaw('coalesce(sum(case when status = ? then 1 else 0 end), 0) as lost_count', [$lost])
            ->selectRaw('coalesce(sum(case when status = ? then amount else 0 end), 0) as open_amount', [$open])
            ->selectRaw('coalesce(sum(case when status = ? then amount else 0 end), 0) as won_amount', [$won])
            ->groupBy('user_id')
            ->get();

        $names = ResponsibleNames::map();

        $rows = $rows->map(fn ($row): array => [
            'user_id' => $row->user_id === null ? null : (int) $row->user_id,
            'name' => $names[(int) $row->user_id] ?? __('reports.unassigned'),
            'total' => (int) $row->total,
            'open' => (int) $row->open_count,
            'won' => (int) $row->won_count,
            'lost' => (int) $row->lost_count,
            'openAmount' => $this->money($row->open_amount),
            'wonAmount' => $this->money($row->won_amount),
        ]);

        // El orden se resuelve aquí y no en la consulta porque el nombre del
        // responsable no está en la tabla: es una relación. Volumen primero y
        // nombre después, para que la tabla sea estable entre dos cargas.
        return $rows
            ->sortBy([
                fn (array $a, array $b): int => $b['total'] <=> $a['total'],
                fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']),
            ])
            ->values()
            ->all();
    }

    /**
     * La consulta base del reporte: el modelo con los filtros ya puestos. Se
     * construye desde Opportunity::query() para que cada medida agregue lo
     * suyo y el conteo se quede en la base de datos.
     *
     * @return Builder<Opportunity>
     */
    private function query(ReportFilters $filters): Builder
    {
        return $filters->apply(Opportunity::query(), self::DATE_COLUMN);
    }

    /**
     * Estado que impone una etapa de cierre, leído de
     * config/opportunities.php como en App\Http\Controllers\DashboardController:
     * una sola fuente para "ganada" y "perdida".
     */
    private function closingStatus(string $stage): string
    {
        return config('opportunities.closing_stages')[$stage] ?? $stage;
    }

    /**
     * Importe con dos decimales y punto decimal, como cadena. El motor
     * devuelve el decimal como texto y el navegador lo formatea como en el
     * dashboard; un vacío y un null se ven igual, que es lo que se quiere.
     */
    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
