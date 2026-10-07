<?php

namespace App\Http\Controllers\Opportunities;

use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DeleteOpportunity;
use App\Actions\Opportunities\UpdateOpportunity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Opportunities\StoreOpportunityRequest;
use App\Http\Requests\Opportunities\UpdateOpportunityRequest;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class OpportunityController extends Controller
{
    /**
     * Listado con búsqueda por nombre o cliente, y filtros por etapa, estado,
     * responsable y cliente.
     *
     * El filtro por responsable es para consultar, no para ocultar: el alcance
     * aplaza las reglas de propiedad, así que con opportunities.view se ve la
     * lista completa.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'stage' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'string'],
        ]);

        $sortable = config('opportunities.sortable');
        $sort = in_array($filters['sort'] ?? null, $sortable, true)
            ? $filters['sort']
            : config('opportunities.default_sort');
        $direction = in_array($filters['direction'] ?? null, config('opportunities.sort_directions'), true)
            ? $filters['direction']
            : 'desc';

        $opportunities = Opportunity::query()
            // Con las columnas que los labels agregados necesitan, misma razón
            // que en el listado de contactos.
            ->with(['client:id,name,type,status', 'contact:id,client_id,first_name,last_name', 'user:id,name'])
            ->search($filters['search'] ?? null)
            ->stage($filters['stage'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($filters['client_id'] ?? null, fn ($query, $clientId) => $query->where('client_id', $clientId))
            ->orderBy($sort, $direction)
            ->paginate(config('crm.per_page'))
            ->withQueryString();

        return Inertia::render('Opportunities/Index', [
            'opportunities' => $opportunities,
            'filters' => array_merge($filters, [
                'sort' => $sort,
                'direction' => $direction,
            ]),
            'stages' => config('opportunities.stages'),
            'statuses' => config('opportunities.statuses'),
            'sortable' => $sortable,
            'clients' => $this->clientOptions(),
            'users' => $this->userOptions(),
            'can' => $this->opportunityPermissions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Opportunities/Create', [
            'clients' => $this->clientOptions(),
            'users' => $this->userOptions(),
            'contacts' => $this->contactOptions(),
            'stages' => $this->openStages(),
            'initialStage' => config('opportunities.initial_stage'),
            'can' => $this->opportunityPermissions(),
        ]);
    }

    public function store(StoreOpportunityRequest $request, CreateOpportunity $createOpportunity): RedirectResponse
    {
        $opportunity = $createOpportunity->handle($request->validated());

        return redirect()
            ->route('opportunities.show', $opportunity)
            ->with('success', __('opportunities.created'));
    }

    public function show(Request $request, Opportunity $opportunity): Response
    {
        $opportunity->load(['client', 'contact', 'user']);

        // El seguimiento, solo para quien puede ver actividades, igual que el
        // historial en la ficha del cliente.
        $activities = $request->user()->can('activities.view')
            ? $opportunity->activities()
                ->with(['client:id,name', 'user:id,name'])
                ->orderByDesc('activity_date')
                ->get()
            : null;

        return Inertia::render('Opportunities/Show', [
            'opportunity' => $opportunity,
            'activities' => $activities,
            'stages' => config('opportunities.stages'),
            'statuses' => config('opportunities.statuses'),
            'openStatus' => config('opportunities.open_status'),
            'can' => array_merge($this->opportunityPermissions(), [
                'activities' => [
                    'view' => $request->user()->can('activities.view'),
                    'create' => $request->user()->can('activities.create'),
                ],
            ]),
        ]);
    }

    public function edit(Opportunity $opportunity): Response
    {
        $opportunity->load(['client', 'contact', 'user']);

        return Inertia::render('Opportunities/Edit', [
            'opportunity' => $opportunity,
            'clients' => $this->clientOptions($opportunity->client),
            'users' => $this->userOptions(),
            'contacts' => $this->contactOptions(),
            'stages' => $this->openStages(),
            'can' => $this->opportunityPermissions(),
        ]);
    }

    public function update(
        UpdateOpportunityRequest $request,
        Opportunity $opportunity,
        UpdateOpportunity $updateOpportunity
    ): RedirectResponse {
        $updateOpportunity->handle($opportunity, $request->validated());

        return redirect()
            ->route('opportunities.show', $opportunity)
            ->with('success', __('opportunities.updated'));
    }

    public function destroy(Opportunity $opportunity, DeleteOpportunity $deleteOpportunity): RedirectResponse
    {
        try {
            $deleteOpportunity->handle($opportunity);
        } catch (DomainException $exception) {
            // Con actividades no se borra: se avisa y se conserva para no
            // perder el historial.
            return redirect()
                ->route('opportunities.show', $opportunity)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('opportunities.index')
            ->with('success', __('opportunities.deleted'));
    }

    /**
     * Permisos que no dependen de una oportunidad concreta.
     *
     * Se preguntan por nombre y no por la policy, por la misma razón que en
     * ContactController: es la forma que el Gate siempre puede responder y la
     * que ya comparte HandleInertiaRequests.
     *
     * @return array<string, bool>
     */
    private function opportunityPermissions(): array
    {
        $user = auth()->user();

        return [
            'create' => $user?->can('opportunities.create') ?? false,
            'update' => $user?->can('opportunities.update') ?? false,
            'delete' => $user?->can('opportunities.delete') ?? false,
            'changeStatus' => $user?->can('opportunities.change_status') ?? false,
        ];
    }

    /**
     * Solo clientes activos: el alcance no pide abrir operaciones contra uno
     * dado de baja.
     *
     * En la edición se añade el actual aunque esté inactivo: dejarlo fuera
     * dejaría el desplegable en blanco y facilitaría cambiar el cliente sin
     * querer.
     *
     * @return Collection<int, Client>
     */
    private function clientOptions(?Client $include = null): Collection
    {
        $clients = Client::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($include === null) {
            return $clients;
        }

        // Solo id y name, igual que en las activas, para que la forma del JSON
        // no dependa del cliente que se está editando.
        return $clients->push($include->only('id', 'name'))->unique('id')->values();
    }

    /**
     * Responsables activos: la misma regla que valida el backend, para no
     * ofrecer cuentas que el servidor va a rechazar.
     *
     * @return Collection<int, User>
     */
    private function userOptions(): Collection
    {
        return User::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Etapas que puede asignar el formulario. Las de cierre se alcanzan con la
     * acción dedicada, que exige otro permiso.
     *
     * @return array<string, string>
     */
    private function openStages(): array
    {
        $stages = config('opportunities.stages');

        return array_intersect_key($stages, array_flip(config('opportunities.open_stages')));
    }

    /**
     * Contactos con las columnas del desplegable.
     *
     * Van todos y Vue los agrupa por cliente: el alcance no define un endpoint
     * por cliente y la regla de pertenencia la comprueba el backend.
     *
     * @return Collection<int, Contact>
     */
    private function contactOptions(): Collection
    {
        return Contact::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'client_id', 'first_name', 'last_name']);
    }
}
