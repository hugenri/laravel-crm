<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use LogicException;

/**
 * El tramo de días que el calendario dibuja en pantalla.
 *
 * El calendario no guarda nada: decide el rango de fechas de la vista actual y
 * se lo entrega al controlador. La aritmética vive aquí y no en Vue: si la
 * calculara el navegador, dos zonas horarias pedirían días distintos para el
 * mismo enlace y el botón "anterior" se descolocaría. Las vistas se normalizan
 * a su ancla (mes al día 1, semana a su primer día, día a medianoche) para que
 * la misma URL produzca siempre la misma rejilla y avanzar no acumule deriva.
 * Todo en la zona de config/app.php, la misma de las actividades, sin convertir
 * a la del navegador.
 */
final class CalendarWindow
{
    /**
     * Tope de días que puede dibujar una vista: un mes abarca como máximo seis
     * semanas. Hace fallar de forma ruidosa antes de que una vista nueva pida
     * un año entero de actividades por un solo enlace.
     */
    public const MAX_DAYS = 42;

    /**
     * Días visibles en la vista, en orden ascendente y con forma "Y-m-d".
     *
     * @var list<string>
     */
    private array $days;

    /**
     * @param  string  $view  Clave de la vista: "month", "week" o "day".
     * @param  CarbonImmutable  $anchor  Primer dia de la vista, ya normalizado.
     * @param  CarbonImmutable  $start  Primer dia visible, a las 00:00.
     * @param  CarbonImmutable  $end  Ultimo dia visible, al final del dia.
     */
    private function __construct(
        private readonly string $view,
        private readonly CarbonImmutable $anchor,
        private readonly CarbonImmutable $start,
        private readonly CarbonImmutable $end,
    ) {
        $this->days = $this->buildDays();
    }

    /**
     * Resuelve la vista pedida a su tramo de días.
     *
     * Una vista desconocida o vacía cae en la que declara config/calendar.php.
     * El controlador ya valida antes de llegar aquí: esto no sustituye a esa
     * validación, solo evita que un valor inesperado llegue a un match sin
     * cobertura.
     *
     * @param  string  $view  Clave pedida por el usuario.
     * @param  CarbonImmutable|null  $anchor  Dia de referencia. Sin el, se usa
     *                                        el dia de hoy de la aplicacion.
     */
    public static function make(string $view, ?CarbonImmutable $anchor = null): self
    {
        $view = array_key_exists($view, config('calendar.views'))
            ? $view
            : (string) config('calendar.default_view');

        $date = ($anchor ?? CarbonImmutable::now())->startOfDay();
        $weekStartsAt = (int) config('calendar.week_starts_at');

        // En un mes el ancla es el día 1 aunque la rejilla empiece antes: es
        // lo que viaja en la URL y decide a qué mes vuelve el botón de avance.
        $anchorDay = match ($view) {
            'day' => $date,
            'week' => $date->startOfWeek($weekStartsAt),
            default => $date->startOfMonth(),
        };

        // El mes se alinea a la semana completa: si el día 1 no cae en el
        // primer día, la primera celda es el anterior que aún pertenece a la
        // semana; sin esto la rejilla arrancaría en otra columna y cada fila
        // se correría un día.
        $start = $view === 'month' ? $anchorDay->startOfWeek($weekStartsAt) : $anchorDay;

        // El día que cierra la semana es el anterior al que la abre y Carbon
        // va de 0 (domingo) a 6 (sábado); el módulo funciona aunque la semana
        // empiece en domingo.
        $weekEndsAt = ($weekStartsAt + 6) % 7;

        // El mes se extiende hasta el domingo que cierra su última semana:
        // una rejilla con medias filas no se lee como calendario.
        $end = match ($view) {
            'day' => $start->endOfDay(),
            'week' => $start->addDays(6)->endOfDay(),
            default => $anchorDay->endOfMonth()->endOfWeek($weekEndsAt),
        };

        return new self($view, $anchorDay, $start, $end);
    }

    public function view(): string
    {
        return $this->view;
    }

    /**
     * Ancla normalizada; viaja en la URL para que recargar o volver atrás
     * llegue al mismo punto y no a un día desplazado.
     */
    public function anchor(): string
    {
        return $this->anchor->toDateString();
    }

    /**
     * Inicio del tramo, con hora, para comparar contra activity_date.
     */
    public function start(): CarbonImmutable
    {
        return $this->start;
    }

    /**
     * Fin del tramo, con hora al final del último día, para que una actividad
     * de las 23:00 de ese día siga entrando.
     */
    public function end(): CarbonImmutable
    {
        return $this->end;
    }

    /**
     * @return list<string>
     */
    public function days(): array
    {
        return $this->days;
    }

    public function dayCount(): int
    {
        return count($this->days);
    }

    /**
     * Ancla de la vista anterior, para el enlace de retroceso.
     */
    public function previousAnchor(): string
    {
        return match ($this->view) {
            'day' => $this->anchor->subDay()->toDateString(),
            'week' => $this->anchor->subWeek()->toDateString(),
            default => $this->anchor->subMonthNoOverflow()->toDateString(),
        };
    }

    /**
     * Ancla de la vista siguiente, para el enlace de avance.
     */
    public function nextAnchor(): string
    {
        return match ($this->view) {
            'day' => $this->anchor->addDay()->toDateString(),
            'week' => $this->anchor->addWeek()->toDateString(),
            default => $this->anchor->addMonthNoOverflow()->toDateString(),
        };
    }

    /**
     * Lo que Vue necesita para pintar el encabezado y la rejilla. Los días van
     * como cadenas: no hay que convertirlos y compararlos con un evento es una
     * igualdad de texto.
     *
     * @return array{
     *     view: string,
     *     anchor: string,
     *     start: string,
     *     end: string,
     *     days: list<string>,
     *     day_count: int,
     *     previous: string,
     *     next: string,
     *     today: string
     * }
     */
    public function toArray(): array
    {
        return [
            'view' => $this->view,
            'anchor' => $this->anchor(),
            'start' => $this->start->toDateString(),
            'end' => $this->end->toDateString(),
            'days' => $this->days,
            'day_count' => $this->dayCount(),
            'previous' => $this->previousAnchor(),
            'next' => $this->nextAnchor(),
            'today' => CarbonImmutable::now()->toDateString(),
        ];
    }

    /**
     * @return list<string>
     */
    private function buildDays(): array
    {
        $days = [];

        for ($day = $this->start->copy(); $day->lessThanOrEqualTo($this->end); $day = $day->addDay()) {
            $days[] = $day->toDateString();
        }

        if (count($days) > self::MAX_DAYS) {
            throw new LogicException(sprintf(
                'La vista "%s" abarca %d dias y el tope es %d.',
                $this->view,
                count($days),
                self::MAX_DAYS,
            ));
        }

        return $days;
    }
}
