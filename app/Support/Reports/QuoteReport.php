<?php

namespace App\Support\Reports;

use App\Models\Quote;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reporte de cotizaciones: conteo y monto por estado.
 *
 * El periodo lo delimita "issue_date", la fecha de emisión, la única fecha de
 * negocio que la tabla guarda además de "created_at" y la que el cliente
 * conoce y busca en el listado. Además es una columna date, así que el rango
 * inclusivo del filtro no depende de la hora.
 *
 * Se cuenta el estado de la columna, no el derivado: App\Models\Quote saca
 * "vencida" de la vigencia vencida, que es una regla de presentación, y el
 * listado filtra por la columna a propósito (filtra por "vencida" trae las que
 * alguien marcó, no las que vencieron solas). Repetir esa derivación en SQL
 * pondría la regla en dos sitios, que es como un reporte empieza a discrepar
 * del módulo que informa.
 */
final class QuoteReport
{
    /**
     * Columna que delimita el periodo: la fecha de emisión.
     */
    private const DATE_COLUMN = 'issue_date';

    /**
     * Totales del periodo: cuántas cotizaciones hay y cuánto suman.
     *
     * Las tres cifras salen de una sola consulta sin agrupar: sumar en SQL
     * mantiene el decimal en el motor, que es donde la columna vive como
     * decimal exacto, y evita volver a pasar los importes por float.
     *
     * @return array{total: int, totalAmount: string, acceptedAmount: string}
     */
    public function summary(ReportFilters $filters): array
    {
        $accepted = (string) config('quotes.accepted_status');

        $row = $this->query($filters)
            ->selectRaw('count(*) as total')
            ->selectRaw('coalesce(sum(total), 0) as total_amount')
            ->selectRaw('coalesce(sum(case when status = ? then total else 0 end), 0) as accepted_amount', [$accepted])
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'totalAmount' => $this->money($row->total_amount ?? 0),
            'acceptedAmount' => $this->money($row->accepted_amount ?? 0),
        ];
    }

    /**
     * Conteo y monto del periodo por estado.
     *
     * Se recorre el catálogo de config/quotes.php y no lo que devuelve la
     * consulta, para que los cinco estados aparezcan siempre y los vacíos con
     * cero. Los estados del listado salen del mismo mapa.
     *
     * @return list<array{status: string, label: string, count: int, amount: string}>
     */
    public function byStatus(ReportFilters $filters): array
    {
        $rows = $this->query($filters)
            ->selectRaw('status, count(*) as total, coalesce(sum(total), 0) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $statuses = config('quotes.statuses');

        $report = [];

        foreach ($statuses as $status => $label) {
            $report[] = [
                'status' => $status,
                'label' => $label,
                'count' => (int) ($rows[$status]->total ?? 0),
                'amount' => $this->money($rows[$status]->amount ?? 0),
            ];
        }

        return $report;
    }

    /**
     * La consulta base del reporte: el modelo con los filtros ya puestos.
     *
     * @return Builder<Quote>
     */
    private function query(ReportFilters $filters): Builder
    {
        return $filters->apply(Quote::query(), self::DATE_COLUMN);
    }

    /**
     * Importe con dos decimales y punto decimal, como cadena, igual que en
     * App\Support\Reports\OpportunityReport y en el dashboard.
     */
    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
