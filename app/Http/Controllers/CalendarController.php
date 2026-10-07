<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Support\CalendarWindow;
use App\Support\SelectOptions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Calendario de actividades.
 *
 * No tiene datos propios: dibuja las mismas actividades del listado, con los
 * mismos scopes, así que el único permiso que necesita es activities.view.
 *
 * El backend decide qué días se pintan: la vista y el ancla viajan en la URL y
 * el tramo, los días y los enlaces llegan ya resueltos. Si el navegador
 * calculara el rango, dos equipos en zonas horarias distintas pedirían días
 * distintos para el mismo enlace.
 */
class CalendarController extends Controller
{
    /**
     * Vista de mes, semana o día, con los mismos filtros que el listado.
     *
     * "mine" no agrega un filtro nuevo: es el responsable con el id de la
     * sesión, para no obligar a elegirse a uno mismo en el desplegable. Vista,
     * tipo y estado se validan contra el catálogo de config: un valor fuera de
     * él es un enlace mal escrito, no un filtro que solo devuelve cero filas.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'view' => ['nullable', Rule::in(array_keys(config('calendar.views')))],
            'anchor' => ['nullable', 'date_format:Y-m-d'],
            'type' => ['nullable', Rule::in(array_keys(config('activities.types')))],
            'status' => ['nullable', Rule::in(array_keys(config('activities.statuses')))],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'mine' => ['nullable', 'boolean'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'opportunity_id' => ['nullable', 'integer', 'exists:opportunities,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ], [
            'view.in' => __('calendar.invalid_view'),
            'anchor.date_format' => __('calendar.invalid_anchor'),
        ]);

        $window = CalendarWindow::make(
            (string) ($filters['view'] ?? config('calendar.default_view')),
            $this->anchor($filters['anchor'] ?? null),
        );

        $events = $this->events($request, $window, $filters);

        return Inertia::render('Calendar/Index', [
            // El tramo ya viene resuelto del backend. Se llama "range" y no
            // "window" para no tapar el objeto global del navegador.
            'range' => $window->toArray(),

            // Catálogo con clave, igual que en Activities, para que el selector
            // no repita los mismos nombres.
            'views' => config('calendar.views'),
            'types' => config('activities.types'),
            'statuses' => config('activities.statuses'),

            'events' => $events,
            'filters' => array_merge($filters, [
                'view' => $window->view(),
                'anchor' => $window->anchor(),
            ]),

            'clients' => SelectOptions::clients(),
            'users' => SelectOptions::users(),
            'opportunities' => SelectOptions::opportunities(),

            'can' => [
                'create' => $request->user()->can('activities.create'),
            ],
        ]);
    }

    /**
     * Actividades del tramo, aplanadas al formato que consume la rejilla.
     *
     * Siempre acotadas al rango de la vista, que no pasa de seis semanas. La
     * búsqueda por texto vive en el listado de Activities: la celda no tiene
     * dónde escribirla.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function events(Request $request, CalendarWindow $window, array $filters): Collection
    {
        $user = $request->user();

        return Activity::query()
            ->with([
                'client:id,name',
                'opportunity:id,name',
                'user:id,name',
            ])
            ->type($filters['type'] ?? null)
            ->status($filters['status'] ?? null)
            ->when($filters['user_id'] ?? null, fn (Builder $query, $userId) => $query->where('user_id', $userId))
            ->when($filters['mine'] ?? false, fn (Builder $query) => $query->where('user_id', $user->getKey()))
            ->when($filters['client_id'] ?? null, fn (Builder $query, $clientId) => $query->where('client_id', $clientId))
            ->when($filters['opportunity_id'] ?? null, fn (Builder $query, $opportunityId) => $query->where('opportunity_id', $opportunityId))
            ->dateBetween($filters['date_from'] ?? null, $filters['date_to'] ?? null)

            // Con hora y no con whereDate, para que la comparación sea
            // sargable y use el índice de activity_date. El rango que haya
            // puesto el usuario lo acota el scope, que trabaja por día.
            ->whereBetween('activity_date', [$window->start(), $window->end()])

            // El id desempata las del mismo instante para que el orden no
            // cambie entre dos cargas.
            ->orderBy('activity_date')
            ->orderBy('id')
            ->get()
            ->map(fn (Activity $activity) => $this->event($activity))
            ->values();
    }

    /**
     * Actividad traducida al formato de la rejilla.
     *
     * La fecha se parte en "date" y "time" porque la columna guarda un
     * datetime: así Vue dibuja el día sin interpretar zonas horarias y lee la
     * hora sin recortar el texto.
     *
     * No lleva fecha de fin: la tabla no tiene y el alcance no pide calcular
     * una, proponer una duración sería inventar el dato. Día completo se deduce
     * de que la hora sea 00:00.
     *
     * @return array<string, mixed>
     */
    private function event(Activity $activity): array
    {
        $date = $activity->activity_date;
        $time = $date->format('H:i');

        return [
            'id' => $activity->getKey(),
            'date' => $date->toDateString(),
            'time' => $time,
            'all_day' => $time === '00:00',

            // El recorte va aquí para que las tres vistas midan igual: si cada
            // una recorta por su lado, el mismo seguimiento se ve de otro largo.
            'title' => Str::limit(trim($activity->description), (int) config('calendar.title_max')),

            'type' => $activity->type,
            'type_label' => $activity->type_label,
            'status' => $activity->status,
            'status_label' => $activity->status_label,

            // Solo id y nombre: es lo que la rejilla y los enlaces necesitan.
            'user' => $activity->user?->only(['id', 'name']),
            'client' => $activity->client?->only(['id', 'name']),
            'opportunity' => $activity->opportunity?->only(['id', 'name']),

            // La ruta se arma en el backend para que Vue no tenga que saber
            // cómo se construye una de activities.
            'url' => route('activities.show', $activity),
        ];
    }

    /**
     * Ancla ya validada con date_format. CarbonImmutable porque CalendarWindow
     * normaliza el valor recibido en vez de tocarlo.
     */
    private function anchor(?string $anchor): ?CarbonImmutable
    {
        if ($anchor === null) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $anchor)->startOfDay();
    }
}
