<?php

namespace App\Http\Controllers\Quotes;

use App\Actions\Quotes\ChangeQuoteStatus;
use App\Actions\Quotes\DeleteQuote;
use App\Actions\Quotes\SaveQuote;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quotes\StoreQuoteRequest;
use App\Http\Requests\Quotes\UpdateQuoteRequest;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    /**
     * Listado de cotizaciones con búsqueda por folio o cliente, y filtros por
     * estado, cliente, oportunidad y responsable.
     *
     * El filtro por responsable es para consultar, no para ocultar: con
     * quotes.view se ve la lista completa.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'opportunity_id' => ['nullable', 'integer', 'exists:opportunities,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'string'],
        ]);

        $sortable = config('quotes.sortable');
        $sort = in_array($filters['sort'] ?? null, $sortable, true)
            ? $filters['sort']
            : config('quotes.default_sort');
        $direction = in_array($filters['direction'] ?? null, config('quotes.sort_directions'), true)
            ? $filters['direction']
            : 'desc';

        $quotes = Quote::query()
            ->with(['client:id,name', 'user:id,name'])
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null)
            ->when($filters['client_id'] ?? null, fn ($query, $id) => $query->where('client_id', $id))
            ->when($filters['opportunity_id'] ?? null, fn ($query, $id) => $query->where('opportunity_id', $id))
            ->when($filters['user_id'] ?? null, fn ($query, $id) => $query->where('user_id', $id))
            ->orderBy($sort, $direction)
            ->paginate(config('crm.per_page'))
            ->withQueryString();

        return Inertia::render('Quotes/Index', [
            'quotes' => $quotes,
            'filters' => array_merge($filters, [
                'sort' => $sort,
                'direction' => $direction,
            ]),
            'statuses' => config('quotes.statuses'),
            'sortable' => $sortable,
            'clients' => $this->clientOptions(),
            'users' => $this->userOptions(),
            'can' => $this->quotePermissions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Quotes/Create', [
            'clients' => $this->clientOptions(),
            'users' => $this->userOptions(),
            'contacts' => $this->contactOptions(),
            'opportunities' => $this->opportunityOptions(),
            'taxRate' => number_format((float) config('quotes.tax_rate'), 2, '.', ''),
            'issueDate' => today()->toDateString(),
            'validUntil' => today()->addDays((int) config('quotes.validity_days'))->toDateString(),
        ]);
    }

    public function store(StoreQuoteRequest $request, SaveQuote $saveQuote): RedirectResponse
    {
        try {
            $quote = $saveQuote->create($request->validated());
        } catch (DomainException $exception) {
            // El formulario valida casi todo, pero no el total: cincuenta
            // partidas al máximo podrían no caber en decimal(15, 2), y eso
            // solo se sabe sumando.
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('quotes.show', $quote)
            ->with('success', __('quotes.created'));
    }

    public function show(Request $request, Quote $quote, ChangeQuoteStatus $changeStatus): Response
    {
        $quote->load(['client', 'contact', 'opportunity', 'user', 'items']);

        return Inertia::render('Quotes/Show', [
            'quote' => $quote,
            'statuses' => config('quotes.statuses'),

            // Las calcula la misma acción que valida el cambio, para no
            // ofrecer un botón que el backend va a rechazar. Sin el permiso la
            // lista llega vacía y Vue no pinta nada.
            'availableStatuses' => $request->user()->can('changeStatus', $quote)
                ? $changeStatus->availableFor($quote)
                : [],

            'can' => $this->quotePermissions(),
        ]);
    }

    public function edit(Quote $quote): Response
    {
        $quote->load(['client', 'contact', 'opportunity', 'user', 'items']);

        return Inertia::render('Quotes/Edit', [
            'quote' => $quote,
            'clients' => $this->clientOptions($quote->client),
            'users' => $this->userOptions(),
            'contacts' => $this->contactOptions(),
            'opportunities' => $this->opportunityOptions(),
        ]);
    }

    public function update(
        UpdateQuoteRequest $request,
        Quote $quote,
        SaveQuote $saveQuote
    ): RedirectResponse {
        try {
            $saveQuote->update($quote, $request->validated());
        } catch (DomainException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('quotes.show', $quote)
            ->with('success', __('quotes.updated'));
    }

    public function destroy(Quote $quote, DeleteQuote $deleteQuote): RedirectResponse
    {
        $deleteQuote->handle($quote);

        return redirect()
            ->route('quotes.index')
            ->with('success', __('quotes.deleted'));
    }

    /**
     * Permisos que no dependen de una cotización concreta.
     *
     * Se preguntan por nombre y no por la policy, por la misma razón que en
     * OpportunityController: es la forma que el Gate siempre puede responder y
     * la que ya comparte HandleInertiaRequests.
     *
     * @return array<string, bool>
     */
    private function quotePermissions(): array
    {
        $user = auth()->user();

        return [
            'create' => $user?->can('quotes.create') ?? false,
            'update' => $user?->can('quotes.update') ?? false,
            'delete' => $user?->can('quotes.delete') ?? false,
            'changeStatus' => $user?->can('quotes.change_status') ?? false,
            'pdf' => $user?->can('quotes.pdf') ?? false,
        ];
    }

    /**
     * Solo clientes activos: el alcance no pide cotizar a uno inactivo.
     *
     * En la edición se añade el actual aunque esté inactivo, por la misma
     * razón que en oportunidades: no dejar el desplegable en blanco.
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
     * Contactos con las columnas del desplegable.
     *
     * Van todos y Vue los limita a los del cliente elegido. La regla de
     * pertenencia la comprueba el backend.
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

    /**
     * Oportunidades con las columnas del desplegable, incluido su cliente.
     *
     * Van todas y Vue las limita al cliente elegido: una oportunidad de otra
     * empresa no puede acompañar a una cotización, y eso lo decide el backend.
     *
     * @return Collection<int, Opportunity>
     */
    private function opportunityOptions(): Collection
    {
        return Opportunity::query()
            ->orderBy('name')
            ->get(['id', 'client_id', 'name', 'status']);
    }
}
