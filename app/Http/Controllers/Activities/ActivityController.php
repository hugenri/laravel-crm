<?php

namespace App\Http\Controllers\Activities;

use App\Actions\Activities\CreateActivity;
use App\Actions\Activities\DeleteActivity;
use App\Actions\Activities\UpdateActivity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Activities\StoreActivityRequest;
use App\Http\Requests\Activities\UpdateActivityRequest;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Opportunity;
use App\Models\User;
use App\Support\SelectOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    /**
     * Listado con búsqueda por descripción o nombre del cliente, y filtros por
     * tipo, estado, responsable, cliente, oportunidad y rango de fechas.
     *
     * El filtro por responsable es para consultar, no para ocultar: con
     * activities.view se ve la lista completa, igual que en oportunidades.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'opportunity_id' => ['nullable', 'integer', 'exists:opportunities,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'string'],
        ]);

        $sortable = config('activities.sortable');
        $sort = in_array($filters['sort'] ?? null, $sortable, true)
            ? $filters['sort']
            : config('activities.default_sort');
        $direction = in_array($filters['direction'] ?? null, config('activities.sort_directions'), true)
            ? $filters['direction']
            : 'desc';

        $activities = Activity::query()
            ->with([
                'client:id,name',
                'opportunity:id,name',
                'user:id,name',
            ])
            ->search($filters['search'] ?? null)
            ->type($filters['type'] ?? null)
            ->status($filters['status'] ?? null)
            ->when($filters['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($filters['client_id'] ?? null, fn ($query, $clientId) => $query->where('client_id', $clientId))
            ->when($filters['opportunity_id'] ?? null, fn ($query, $opportunityId) => $query->where('opportunity_id', $opportunityId))
            ->dateBetween($filters['date_from'] ?? null, $filters['date_to'] ?? null)
            ->orderBy($sort, $direction)
            ->paginate(config('crm.per_page'))
            ->withQueryString();

        return Inertia::render('Activities/Index', [
            'activities' => $activities,
            'filters' => array_merge($filters, [
                'sort' => $sort,
                'direction' => $direction,
            ]),
            'types' => config('activities.types'),
            'statuses' => config('activities.statuses'),
            'sortable' => $sortable,
            'clients' => $this->clientOptions(),
            'users' => $this->userOptions(),
            'opportunities' => $this->opportunityOptions(),
            'can' => $this->activityPermissions(),
        ]);
    }

    /**
     * Alta. client_id y opportunity_id llegan como preselección desde la ficha
     * del cliente o de la oportunidad, para que la actividad nazca asociada; el
     * store valida la combinación de todos modos.
     *
     * El calendario usa el mismo mecanismo con activity_date, aceptando el
     * formato del input datetime-local: un día sin hora no tendría qué
     * preescribir en el campo.
     */
    public function create(Request $request): Response
    {
        $preselect = $request->validate([
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'opportunity_id' => ['nullable', 'integer', 'exists:opportunities,id'],
            'activity_date' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ]);

        $clientId = $this->preselectClient($preselect['client_id'] ?? null);
        $opportunityId = $this->preselectOpportunity($preselect['opportunity_id'] ?? null, $clientId);

        // Si solo llegó la oportunidad, el cliente se infiere de ella: si no,
        // quedaría el desplegable de cliente vacío con la oportunidad elegida.
        if ($opportunityId !== null && $clientId === null) {
            $opportunity = Opportunity::find($opportunityId);
            $clientId = $this->preselectClient((string) $opportunity->client_id);
        }

        return Inertia::render('Activities/Create', [
            'clients' => $this->clientOptions(),
            'users' => $this->userOptions(),
            'opportunities' => $this->opportunityOptions(),
            'types' => config('activities.types'),
            'statuses' => config('activities.statuses'),
            'initialStatus' => config('activities.initial_status'),
            'initialClientId' => $clientId,
            'initialOpportunityId' => $opportunityId,
            'initialActivityDate' => $preselect['activity_date'] ?? null,
            'can' => $this->activityPermissions(),
        ]);
    }

    public function store(StoreActivityRequest $request, CreateActivity $createActivity): RedirectResponse
    {
        $activity = $createActivity->handle($request->validated());

        return redirect()
            ->route('activities.show', $activity)
            ->with('success', __('activities.created'));
    }

    public function show(Activity $activity): Response
    {
        $activity->load(['client', 'opportunity', 'user']);

        return Inertia::render('Activities/Show', [
            'activity' => $activity,
            'types' => config('activities.types'),
            'statuses' => config('activities.statuses'),
            'can' => $this->activityPermissions(),
        ]);
    }

    public function edit(Activity $activity): Response
    {
        $activity->load(['client', 'opportunity', 'user']);

        return Inertia::render('Activities/Edit', [
            'activity' => $activity,
            'clients' => $this->clientOptions($activity->client),
            'users' => $this->userOptions(),
            'opportunities' => $this->opportunityOptions(),
            'types' => config('activities.types'),
            'statuses' => config('activities.statuses'),
            'can' => $this->activityPermissions(),
        ]);
    }

    public function update(
        UpdateActivityRequest $request,
        Activity $activity,
        UpdateActivity $updateActivity
    ): RedirectResponse {
        $updateActivity->handle($activity, $request->validated());

        return redirect()
            ->route('activities.show', $activity)
            ->with('success', __('activities.updated'));
    }

    public function destroy(Activity $activity, DeleteActivity $deleteActivity): RedirectResponse
    {
        $deleteActivity->handle($activity);

        return redirect()
            ->route('activities.index')
            ->with('success', __('activities.deleted'));
    }

    /**
     * Permisos que no dependen de una actividad concreta, leídos por nombre de
     * permiso.
     *
     * @return array<string, bool>
     */
    private function activityPermissions(): array
    {
        $user = auth()->user();

        return [
            'create' => $user?->can('activities.create') ?? false,
            'update' => $user?->can('activities.update') ?? false,
            'delete' => $user?->can('activities.delete') ?? false,
        ];
    }

    /**
     * Clientes activos. En la edición se incluye el actual aunque esté
     * inactivo, por la misma razón que en oportunidades.
     *
     * @return Collection<int, Client>
     */
    private function clientOptions(?Client $include = null): Collection
    {
        return SelectOptions::clients($include);
    }

    /**
     * Responsables activos.
     *
     * @return Collection<int, User>
     */
    private function userOptions(): Collection
    {
        return SelectOptions::users();
    }

    /**
     * Oportunidades del desplegable. Van todas y Vue las agrupa por cliente;
     * que la oportunidad pertenezca al cliente lo comprueba el backend.
     *
     * @return Collection<int, Opportunity>
     */
    private function opportunityOptions(): Collection
    {
        return SelectOptions::opportunities();
    }

    /**
     * Solo se preselecciona si el cliente sigue activo, porque de lo contrario
     * el catálogo no lo va a ofrecer.
     */
    private function preselectClient(mixed $clientId): ?int
    {
        if ($clientId === null || $clientId === '') {
            return null;
        }

        $client = Client::find((int) $clientId);

        return $client?->isActive() ? $client->getKey() : null;
    }

    /**
     * Se honra solo si el cliente está activo y, cuando llega un cliente junto
     * con ella, si es el mismo: no conviene dejar el formulario con una pareja
     * que el backend va a rechazar.
     */
    private function preselectOpportunity(mixed $opportunityId, ?int $clientId): ?int
    {
        if ($opportunityId === null || $opportunityId === '') {
            return null;
        }

        $opportunity = Opportunity::find((int) $opportunityId);

        if ($opportunity === null || ! $this->preselectClient((string) $opportunity->client_id)) {
            return null;
        }

        if ($clientId !== null && $opportunity->client_id !== $clientId) {
            return null;
        }

        return $opportunity->getKey();
    }
}
