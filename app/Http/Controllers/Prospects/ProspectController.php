<?php

namespace App\Http\Controllers\Prospects;

use App\Actions\Prospects\DeleteProspect;
use App\Actions\Prospects\SaveProspect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Prospects\StoreProspectRequest;
use App\Http\Requests\Prospects\UpdateProspectRequest;
use App\Models\Prospect;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ProspectController extends Controller
{
    /**
     * Listado del embudo con búsqueda por nombre, empresa, teléfono o correo, y
     * filtros por estado, fuente y responsable.
     *
     * El filtro por responsable es para consultar, no para ocultar: el alcance
     * aplaza las reglas de propiedad, así que con prospects.view se ve la lista
     * completa.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string'],
            'source' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'string'],
        ]);

        // Lista blanca tomada de la configuración: la columna recibida nunca
        // se interpola en la consulta.
        $sortable = config('prospects.sortable');
        $sort = in_array($filters['sort'] ?? null, $sortable, true)
            ? $filters['sort']
            : config('prospects.default_sort');
        $direction = in_array($filters['direction'] ?? null, config('prospects.sort_directions'), true)
            ? $filters['direction']
            : 'desc';

        $prospects = Prospect::query()
            // Responsable con las columnas que la vista necesita, misma razón
            // que en oportunidades.
            ->with('user:id,name')
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null)
            ->source($filters['source'] ?? null)
            ->when($filters['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->orderBy($sort, $direction)
            ->paginate(config('crm.per_page'))
            ->withQueryString();

        return Inertia::render('Prospects/Index', [
            'prospects' => $prospects,
            'filters' => array_merge($filters, [
                'sort' => $sort,
                'direction' => $direction,
            ]),
            'statuses' => config('prospects.statuses'),
            'sources' => config('prospects.sources'),
            'sortable' => $sortable,
            'users' => $this->userOptions(),
            'can' => $this->prospectPermissions(),
        ]);
    }

    public function show(Request $request, Prospect $prospect): Response
    {
        $prospect->load(['user', 'client', 'contact']);

        return Inertia::render('Prospects/Show', [
            'prospect' => $prospect,

            // El catálogo completo viaja para poder pintar la etiqueta de un
            // estado ya alcanzado, pero como destino de cambio solo se ofrecen
            // los que no son convertidos.
            'statuses' => config('prospects.statuses'),
            'qualifiedStatuses' => Prospect::qualifiedStatuses(),
            'convertedStatus' => config('prospects.converted_status'),
            'can' => $this->prospectPermissions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Prospects/Create', [
            'sources' => config('prospects.sources'),
            'statuses' => $this->qualifiedStatuses(),
            'initialStatus' => config('prospects.initial_status'),
            'users' => $this->userOptions(),
        ]);
    }

    public function store(StoreProspectRequest $request, SaveProspect $saveProspect): RedirectResponse
    {
        $prospect = $saveProspect->create($request->validated());

        return redirect()
            ->route('prospects.show', $prospect)
            ->with('success', __('prospects.created'));
    }

    public function edit(Prospect $prospect): Response
    {
        $prospect->load('user');

        return Inertia::render('Prospects/Edit', [
            'prospect' => $prospect,
            'sources' => config('prospects.sources'),
            'statuses' => $this->qualifiedStatuses(),
            'users' => $this->userOptions(),
        ]);
    }

    public function update(UpdateProspectRequest $request, Prospect $prospect, SaveProspect $saveProspect): RedirectResponse
    {
        $saveProspect->update($prospect, $request->validated());

        return redirect()
            ->route('prospects.show', $prospect)
            ->with('success', __('prospects.updated'));
    }

    public function destroy(Prospect $prospect, DeleteProspect $deleteProspect): RedirectResponse
    {
        try {
            $deleteProspect->handle($prospect);
        } catch (DomainException $exception) {
            // Un convertido es el registro de quien entró a la cartera: se
            // conserva y se avisa, igual que un cliente con contactos.
            return redirect()
                ->route('prospects.show', $prospect)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('prospects.index')
            ->with('success', __('prospects.deleted'));
    }

    /**
     * Estados que ofrecen el formulario y la acción de cambio, ya recortados
     * del catálogo para que Vue no tenga que volver a filtrarlos.
     *
     * @return array<string, string>
     */
    private function qualifiedStatuses(): array
    {
        return array_intersect_key(
            config('prospects.statuses'),
            array_flip(Prospect::qualifiedStatuses()),
        );
    }

    /**
     * Permisos que no dependen de un prospecto concreto.
     *
     * Se preguntan por nombre y no por la policy, por la misma razón que en
     * ContactController: es la forma que el Gate siempre puede responder y la
     * que ya comparte HandleInertiaRequests.
     *
     * @return array<string, bool>
     */
    private function prospectPermissions(): array
    {
        $user = auth()->user();

        return [
            'create' => $user?->can('prospects.create') ?? false,
            'update' => $user?->can('prospects.update') ?? false,
            'delete' => $user?->can('prospects.delete') ?? false,
            'changeStatus' => $user?->can('prospects.change_status') ?? false,
            'convert' => $user?->can('prospects.convert') ?? false,
        ];
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
}
