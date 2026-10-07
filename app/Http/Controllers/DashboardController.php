<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Opportunity;
use App\Models\Prospect;
use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Resumen operativo del CRM.
 *
 * Cada bloque se arma solo si el usuario puede ver ese módulo; si no, la prop
 * llega como null y Vue no la dibuja. Los números salen de agregaciones y las
 * listas van con limit.
 */
class DashboardController extends Controller
{
    /**
     * Tope de filas de cada lista resumida. El listado completo vive en el
     * módulo al que lleva un enlace cada tarjeta.
     */
    private const LIST_LIMIT = 5;

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->isActive(),
                'email_verified' => $user->hasVerifiedEmail(),
                'registered_at' => $user->created_at?->toIso8601String(),
            ],

            'clients' => $user->can('clients.view') ? $this->clientSummary() : null,
            'opportunities' => $user->can('opportunities.view') ? $this->opportunitySummary() : null,
            'activities' => $user->can('activities.view') ? $this->activitySummary() : null,
            'prospects' => $user->can('prospects.view') ? $this->prospectSummary() : null,
            'quotes' => $user->can('quotes.view') ? $this->quoteSummary() : null,
        ]);
    }

    /**
     * @return array{active: int, inactive: int, recent: Collection<int, Client>}
     */
    private function clientSummary(): array
    {
        return [
            // Por exclusión, para no repetir aquí el valor del otro estado,
            // que vive en config/clients.php.
            'active' => Client::query()->active()->count(),
            'inactive' => Client::query()
                ->where('status', '!=', config('clients.active_status'))
                ->count(),
            'recent' => Client::query()
                ->orderByDesc('created_at')
                ->limit(self::LIST_LIMIT)
                ->get(['id', 'name', 'type', 'status', 'created_at']),
        ];
    }

    /**
     * @return array{
     *     open: int,
     *     won: int,
     *     lost: int,
     *     wonAmount: string,
     *     byStage: array<int, array{stage: string, label: string, count: int}>,
     *     openList: Collection<int, Opportunity>
     * }
     */
    private function opportunitySummary(): array
    {
        $won = $this->closingStatus('ganada');
        $lost = $this->closingStatus('perdida');

        return [
            'open' => Opportunity::query()->open()->count(),
            'won' => $won === null ? 0 : Opportunity::query()->where('status', $won)->count(),
            'lost' => $lost === null ? 0 : Opportunity::query()->where('status', $lost)->count(),

            // Una venta es una oportunidad ganada: no hay entidad de ventas.
            // Dos decimales fijos para que el texto llegue siempre igual a Vue.
            'wonAmount' => $won === null
                ? '0.00'
                : number_format((float) Opportunity::query()->where('status', $won)->sum('amount'), 2, '.', ''),

            'byStage' => $this->opportunitiesByStage(),

            // Las que cierran antes primero; sin fecha estimada van al final.
            'openList' => Opportunity::query()
                ->open()
                ->with(['client:id,name', 'user:id,name'])
                ->orderByRaw('CASE WHEN expected_close_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expected_close_date')
                ->limit(self::LIST_LIMIT)
                ->get(['id', 'client_id', 'user_id', 'name', 'amount', 'stage', 'expected_close_date']),
        ];
    }

    /**
     * Oportunidades abiertas agrupadas por etapa.
     *
     * Se recorre el catálogo de config y no la consulta: así una etapa vacía
     * sale con cero y solo aparecen las abiertas, sin ganada ni perdida.
     *
     * @return array<int, array{stage: string, label: string, count: int}>
     */
    private function opportunitiesByStage(): array
    {
        $counts = Opportunity::query()
            ->open()
            ->selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $stages = config('opportunities.stages');

        $rows = [];

        foreach (config('opportunities.open_stages') as $stage) {
            $rows[] = [
                'stage' => $stage,
                'label' => $stages[$stage],
                'count' => (int) ($counts[$stage] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @return array{
     *     pending: int,
     *     completed: int,
     *     cancelled: int,
     *     pendingList: Collection<int, Activity>,
     *     recent: Collection<int, Activity>
     * }
     */
    private function activitySummary(): array
    {
        return [
            'pending' => $this->activityCount('pendiente'),
            'completed' => $this->activityCount('completada'),
            'cancelled' => $this->activityCount('cancelada'),

            // Las pendientes más viejas primero: lo que lleva más sin
            // resolverse es lo primero que se debería ver.
            'pendingList' => $this->activityList('pendiente', 'asc'),
            'recent' => $this->activityList(null, 'desc'),
        ];
    }

    /**
     * Misma clave de estado que usa el resto de la app, la que declara
     * config/activities.php.
     */
    private function activityCount(string $status): int
    {
        return Activity::query()->status($status)->count();
    }

    /**
     * Columnas mínimas de las dos listas del panel. La descripción viaja
     * entera y el recorte a una línea lo hace el navegador.
     *
     * @return Collection<int, Activity>
     */
    private function activityList(?string $status, string $direction): Collection
    {
        return Activity::query()
            ->status($status)
            ->with(['client:id,name', 'opportunity:id,name', 'user:id,name'])
            ->orderBy('activity_date', $direction)
            ->limit(self::LIST_LIMIT)
            ->get([
                'id',
                'client_id',
                'opportunity_id',
                'user_id',
                'type',
                'status',
                'activity_date',
                'description',
            ]);
    }

    /**
     * Estado que impone una etapa de cierre, leído de
     * config/opportunities.php para no repetir el catálogo aquí.
     */
    private function closingStatus(string $stage): ?string
    {
        return config('opportunities.closing_stages')[$stage] ?? null;
    }

    /**
     * Embudo de prospectos: solo el estado inicial, que es lo que pide el
     * alcance como indicador de trabajo pendiente. El estado sale de
     * config/prospects.php y los ya convertidos no cuentan: salieron del
     * embudo, para eso está el scope open().
     *
     * @return array{new: int, open: int, qualified: int}
     */
    private function prospectSummary(): array
    {
        return [
            'new' => Prospect::query()->status(config('prospects.initial_status'))->count(),
            'open' => Prospect::query()->open()->count(),
            'qualified' => Prospect::query()->status('calificado')->count(),
        ];
    }

    /**
     * Cotizaciones pendientes de respuesta, el único indicador que pide el
     * alcance para este módulo. Los estados salen de config/quotes.php.
     *
     * Una cotización vencida sigue pendiente: caduca la propuesta, no el hecho
     * de que el cliente no haya contestado.
     *
     * @return array{pending: int}
     */
    private function quoteSummary(): array
    {
        return [
            'pending' => Quote::query()->pending()->count(),
        ];
    }
}
