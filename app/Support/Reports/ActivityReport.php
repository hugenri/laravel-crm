<?php

namespace App\Support\Reports;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reporte de seguimiento: actividades pendientes y cómo se reparten.
 *
 * No repite los tres indicadores por estado del dashboard: esos son el estado
 * del día, este es el reparto del trabajo, que es lo que un gerente necesita
 * para ver quién se atrasa.
 *
 * Solo cuenta lo pendiente: una completada o cancelada ya no es trabajo por
 * hacer, y mezclarla desvirtuaría el número.
 *
 * A diferencia de los otros tres reportes usa los scopes status() y
 * dateBetween() de App\Models\Activity, los mismos del listado y el
 * calendario, para que no puedan discrepar.
 */
final class ActivityReport
{
    /**
     * Columna que delimita el periodo.
     */
    private const DATE_COLUMN = 'activity_date';

    /**
     * Estado que cuenta como pendiente, con la misma clave del dashboard y el
     * listado.
     */
    private const PENDING_STATUS = 'pendiente';

    /**
     * Pendientes del periodo y su reparto por responsable.
     *
     * @return array{
     *     pending: int,
     *     byResponsible: list<array{user_id: int|null, name: string, count: int}>
     * }
     */
    public function summarize(ReportFilters $filters): array
    {
        $rows = $this->query($filters)
            ->selectRaw('user_id, count(*) as total')
            ->groupBy('user_id')
            ->get();

        $names = ResponsibleNames::map();

        $byResponsible = $rows->map(fn ($row): array => [
            'user_id' => $row->user_id === null ? null : (int) $row->user_id,
            'name' => $names[(int) $row->user_id] ?? __('reports.unassigned'),
            'count' => (int) $row->total,
        ]);

        return [
            // El total se cuenta en la base de datos y no sumando los grupos
            // ya contados, para que el encabezado no dependa de la tabla. Suman
            // lo mismo, que es la garantía que se busca.
            'pending' => $this->query($filters)->count(),

            // Orden estable: más pendientes primero y, a igual número, por
            // nombre. El orden se resuelve en PHP porque el nombre del
            // responsable es una relación, no una columna.
            'byResponsible' => $byResponsible
                ->sortBy([
                    fn (array $a, array $b): int => $b['count'] <=> $a['count'],
                    fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * La consulta base del reporte: actividades pendientes con los filtros ya
     * puestos.
     *
     * @return Builder<Activity>
     */
    private function query(ReportFilters $filters): Builder
    {
        return $this->queryWithPeriod(
            Activity::query()->status(self::PENDING_STATUS),
            $filters,
        );
    }

    /**
     * Aplica el periodo con el scope del sistema y no con
     * ReportFilters::apply(), que compararía con whereDate. Aquí da igual, pero
     * reutilizar el scope mantiene reporte y calendario diciendo lo mismo sobre
     * el mismo día.
     *
     * @param  Builder<Activity>  $query
     * @return Builder<Activity>
     */
    private function queryWithPeriod(Builder $query, ReportFilters $filters): Builder
    {
        $query->dateBetween($filters->dateFrom, $filters->dateTo);

        if ($filters->userId !== null) {
            $query->where('user_id', $filters->userId);
        }

        return $query;
    }
}
