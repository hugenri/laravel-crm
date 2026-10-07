<?php

namespace App\Http\Controllers\Clients;

use App\Actions\Clients\CreateClient;
use App\Actions\Clients\DeleteClient;
use App\Actions\Clients\UpdateClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Models\Client;
use App\Models\Contact;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        // Lista blanca tomada de la configuración: la columna recibida nunca
        // se interpola en la consulta.
        $sortable = config('clients.sortable');
        $sort = in_array($filters['sort'] ?? null, $sortable, true)
            ? $filters['sort']
            : config('clients.default_sort');

        $direction = ($filters['direction'] ?? null) === 'desc' ? 'desc' : 'asc';

        $clients = Client::query()
            ->search($filters['search'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->orderBy($sort, $direction)
            ->paginate(config('crm.per_page'))
            ->withQueryString();

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => array_merge($filters, [
                'sort' => $sort,
                'direction' => $direction,
            ]),
            'types' => config('clients.types'),
            'statuses' => config('clients.statuses'),
            'activeStatus' => config('clients.active_status'),
            'sortable' => $sortable,
            'can' => [
                'create' => $request->user()->can('clients.create'),
                'update' => $request->user()->can('clients.update'),
                'changeStatus' => $request->user()->can('clients.change_status'),
            ],
        ]);
    }

    /**
     * Ficha del cliente con sus contactos, el contacto principal y su
     * historial comercial.
     */
    public function show(Request $request, Client $client): Response
    {
        $client->load(['contacts' => fn ($query) => $query->orderBy('last_name')->orderBy('first_name')]);

        // Solo para quien puede ver oportunidades, y con las columnas que la
        // vista necesita.
        $opportunities = $request->user()->can('opportunities.view')
            ? $client->opportunities()
                ->with('user:id,name')
                ->orderByDesc('created_at')
                ->get()
            : null;

        // El seguimiento, solo para quien puede ver actividades.
        $activities = $request->user()->can('activities.view')
            ? $client->activities()
                ->with(['opportunity:id,name', 'user:id,name'])
                ->orderByDesc('activity_date')
                ->get()
            : null;

        // Las propuestas, por la misma razón que las oportunidades: llegan como
        // null y no vacías, para no confundirlas con un cliente que no tiene.
        $quotes = $request->user()->can('quotes.view')
            ? $client->quotes()
                ->with('user:id,name')
                ->orderByDesc('issue_date')
                ->orderByDesc('id')
                ->get()
            : null;

        return Inertia::render('Clients/Show', [
            'client' => $client,
            'contacts' => $client->contacts,
            'primaryContact' => $client->contacts->firstWhere('is_primary', true),
            'opportunities' => $opportunities,
            'activities' => $activities,
            'quotes' => $quotes,
            'statuses' => config('clients.statuses'),
            'activeStatus' => config('clients.active_status'),
            'can' => [
                'update' => $request->user()->can('update', $client),
                'delete' => $request->user()->can('delete', $client),
                'changeStatus' => $request->user()->can('changeStatus', $client),
                'contacts' => [
                    'create' => $request->user()->can('create', [Contact::class, $client]),
                    'update' => $request->user()->can('contacts.update'),
                    'delete' => $request->user()->can('contacts.delete'),
                    'setPrimary' => $request->user()->can('contacts.set_primary'),
                ],
                'opportunities' => [
                    'view' => $request->user()->can('opportunities.view'),
                ],
                'activities' => [
                    'view' => $request->user()->can('activities.view'),
                    'create' => $request->user()->can('activities.create'),
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Clients/Create', [
            'types' => config('clients.types'),
            'statuses' => config('clients.statuses'),
            'activeStatus' => config('clients.active_status'),
        ]);
    }

    public function store(StoreClientRequest $request, CreateClient $createClient): RedirectResponse
    {
        $client = $createClient->handle($request->validated());

        return redirect()
            ->route('clients.show', $client)
            ->with('success', __('clients.created'));
    }

    public function edit(Client $client): Response
    {
        return Inertia::render('Clients/Edit', [
            'client' => $client,
            'types' => config('clients.types'),
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client, UpdateClient $updateClient): RedirectResponse
    {
        $updateClient->handle($client, $request->safe()->except('status'));

        return redirect()
            ->route('clients.show', $client)
            ->with('success', __('clients.updated'));
    }

    public function destroy(Client $client, DeleteClient $deleteClient): RedirectResponse
    {
        try {
            $deleteClient->handle($client);
        } catch (DomainException $exception) {
            // Con contactos no se borra: se avisa y se sugiere inactivarlo,
            // que es la alternativa que conserva la información.
            return redirect()
                ->route('clients.show', $client)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('clients.index')
            ->with('success', __('clients.deleted'));
    }
}
