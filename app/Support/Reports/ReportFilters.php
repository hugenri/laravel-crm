<?php

namespace App\Support\Reports;

use Illuminate\Database\Eloquent\Builder;

/**
 * Los filtros de la pantalla de reportes, ya validados.
 *
 * Un objeto y no tres parámetros sueltos porque los cuatro reportes los
 * repiten igual; con un "whereDate" por reporte, un cambio de semántica del
 * rango tendría que tocarse en los cuatro y alguno se queda atrás.
 *
 * El rango es de días completos e inclusivo por los dos extremos. Se compara
 * con whereDate y no con un between de datetime porque un "2026-10-01 00:00:00"
 * a "2026-10-31 00:00:00" deja fuera todo lo creado ese último día, que es el
 * error típico de un reporte mensual que no cuadra con el listado. Es el
 * mismo criterio que App\Models\Activity::scopeDateBetween, para que el rango
 * de un reporte y el del calendario signifiquen lo mismo.
 *
 * Da igual que la columna sea datetime o date, por eso el método recibe el
 * nombre de la columna y no decide el tipo de fecha por el módulo.
 */
final class ReportFilters
{
    /**
     * @param  string|null  $dateFrom  Primer dia del periodo, "AAAA-MM-DD".
     * @param  string|null  $dateTo  Ultimo dia del periodo, inclusivo.
     * @param  int|null  $userId  Responsable unico, o null para todos.
     */
    public function __construct(
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
        public readonly ?int $userId = null,
    ) {}

    /**
     * Construye los filtros desde lo que el controlador ya validó.
     *
     * @param  array<string, mixed>  $validated
     */
    public static function from(array $validated): self
    {
        return new self(
            dateFrom: self::date($validated['date_from'] ?? null),
            dateTo: self::date($validated['date_to'] ?? null),
            userId: isset($validated['user_id']) ? (int) $validated['user_id'] : null,
        );
    }

    /**
     * Aplica el periodo y el responsable a una consulta ya preparada. La
     * columna de fecha la pone cada módulo: ver los cuatro reportes.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query, string $dateColumn): Builder
    {
        return $query
            ->when(
                $this->dateFrom,
                fn (Builder $query, string $from): Builder => $query->whereDate($dateColumn, '>=', $from),
            )
            ->when(
                $this->dateTo,
                fn (Builder $query, string $to): Builder => $query->whereDate($dateColumn, '<=', $to),
            )
            ->when(
                $this->userId,
                fn (Builder $query, int $userId): Builder => $query->where('user_id', $userId),
            );
    }

    /**
     * ¿Hay algo que filtrar? Lo usa la página para ofrecer "Limpiar" y para
     * el encabezado del periodo visible.
     */
    public function isFiltered(): bool
    {
        return $this->dateFrom !== null || $this->dateTo !== null || $this->userId !== null;
    }

    /**
     * Un filtro vacío llega como cadena vacía desde un formulario y como null
     * desde una URL sin el parámetro; aquí los dos significan lo mismo.
     */
    private static function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
