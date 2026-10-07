<?php

namespace App\Support\Reports;

use App\Models\Prospect;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reporte de prospectos: embudo por estado y tasa de conversión.
 *
 * El periodo lo delimita "created_at": un prospecto no tiene fecha de
 * contacto, calificación ni conversión, y la de seguimiento es una promesa y
 * no un hecho, así que no sirve para contar. El reporte dice cuántos se
 * capturaron en el periodo y en qué estado están hoy, que es lo que los datos
 * sostienen sin inventar una columna.
 *
 * La conversión es convertidos sobre capturados del periodo, no sobre el
 * histórico: mezclarlos daría un porcentaje que responde a otra pregunta. Con
 * total cero el porcentaje es 0.00, sin disimular una división.
 */
final class ProspectReport
{
    /**
     * Columna que delimita el periodo.
     */
    private const DATE_COLUMN = 'created_at';

    /**
     * Totales del periodo y tasa de conversión.
     *
     * "converted" y "open" son las dos mitades del embudo. La clave del estado
     * terminal sale de config/prospects.php, y "lo que queda" es su
     * complemento, que es justo lo que significa el scope open() de
     * App\Models\Prospect.
     *
     * @return array{total: int, converted: int, open: int, conversionRate: string}
     */
    public function summary(ReportFilters $filters): array
    {
        $converted = (string) config('prospects.converted_status');

        $row = $this->query($filters)
            ->selectRaw('count(*) as total')
            ->selectRaw('coalesce(sum(case when status = ? then 1 else 0 end), 0) as converted_count', [$converted])
            ->selectRaw('coalesce(sum(case when status <> ? then 1 else 0 end), 0) as open_count', [$converted])
            ->first();

        $total = (int) ($row->total ?? 0);
        $convertedCount = (int) ($row->converted_count ?? 0);

        return [
            'total' => $total,
            'converted' => $convertedCount,
            'open' => (int) ($row->open_count ?? 0),

            // Dos decimales en cadena, como los importes, para que el backend
            // entregue siempre el mismo texto y el navegador solo agregue el
            // signo.
            'conversionRate' => $total === 0
                ? '0.00'
                : number_format($convertedCount / $total * 100, 2, '.', ''),
        ];
    }

    /**
     * Conteo del periodo por estado.
     *
     * Se recorre el catálogo de config/prospects.php, así que los cinco
     * estados aparecen siempre y los vacíos con cero. "convertido" también,
     * aunque el formulario no lo ofrezca: si no, el reporte no explicaría su
     * propia tasa de conversión.
     *
     * @return list<array{status: string, label: string, count: int}>
     */
    public function byStatus(ReportFilters $filters): array
    {
        $counts = $this->query($filters)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statuses = config('prospects.statuses');

        $rows = [];

        foreach ($statuses as $status => $label) {
            $rows[] = [
                'status' => $status,
                'label' => $label,
                'count' => (int) ($counts[$status] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * El periodo agrupado por responsable.
     *
     * La tasa se calcula fila por fila y no dividiendo el total de la
     * columna: cada responsable tiene su propio denominador (promediar 1 de 1
     * y 0 de 9 daría 50% cuando el equipo no capturó nada).
     *
     * Solo aparece quien capturó en el periodo; las filas sin responsable se
     * listan con la etiqueta de lang/es/reports.php.
     *
     * @return list<array{
     *     user_id: int|null,
     *     name: string,
     *     total: int,
     *     open: int,
     *     converted: int,
     *     conversionRate: string
     * }>
     */
    public function byResponsible(ReportFilters $filters): array
    {
        $converted = (string) config('prospects.converted_status');

        $rows = $this->query($filters)
            ->selectRaw('user_id')
            ->selectRaw('count(*) as total')
            ->selectRaw('coalesce(sum(case when status = ? then 1 else 0 end), 0) as converted_count', [$converted])
            ->selectRaw('coalesce(sum(case when status <> ? then 1 else 0 end), 0) as open_count', [$converted])
            ->groupBy('user_id')
            ->get();

        $names = ResponsibleNames::map();

        $rows = $rows->map(function ($row) use ($names): array {
            $total = (int) $row->total;
            $convertedCount = (int) $row->converted_count;

            return [
                'user_id' => $row->user_id === null ? null : (int) $row->user_id,
                'name' => $names[(int) $row->user_id] ?? __('reports.unassigned'),
                'total' => $total,
                'open' => (int) $row->open_count,
                'converted' => $convertedCount,
                'conversionRate' => $total === 0
                    ? '0.00'
                    : number_format($convertedCount / $total * 100, 2, '.', ''),
            ];
        });

        return $rows
            ->sortBy([
                fn (array $a, array $b): int => $b['total'] <=> $a['total'],
                fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']),
            ])
            ->values()
            ->all();
    }

    /**
     * La consulta base del reporte: el modelo con los filtros ya puestos.
     *
     * @return Builder<Prospect>
     */
    private function query(ReportFilters $filters): Builder
    {
        return $filters->apply(Prospect::query(), self::DATE_COLUMN);
    }
}
