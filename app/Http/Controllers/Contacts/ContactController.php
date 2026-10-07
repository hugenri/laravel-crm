<?php

namespace App\Http\Controllers\Contacts;

use App\Actions\Contacts\CreateContact;
use App\Actions\Contacts\DeleteContact;
use App\Actions\Contacts\UpdateContact;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contacts\StoreContactRequest;
use App\Http\Requests\Contacts\UpdateContactRequest;
use App\Models\Client;
use App\Models\Contact;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    /**
     * Listado global de contactos, con búsqueda por nombre, correo o cliente.
     * La gestión completa se hace desde la ficha del cliente.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
        ]);

        $contacts = Contact::query()
            // type y status son atributos agregados: si el eager load
            // restringido los deja fuera, serializan como null.
            ->with('client:id,name,type,status')
            ->search($filters['search'] ?? null)
            ->when($filters['client_id'] ?? null, fn ($query, $clientId) => $query->where('client_id', $clientId))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(config('crm.per_page'))
            ->withQueryString();

        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts,
            'filters' => $filters,
            'can' => $this->contactPermissions(),
        ]);
    }

    public function create(Client $client): Response
    {
        // La ruta va anidada bajo el cliente, así que el gate se pregunta por
        // crear un contacto para este cliente, no por el cliente en sí.
        Gate::authorize('create', [Contact::class, $client]);

        return Inertia::render('Contacts/Create', [
            'client' => $client,
            'contacts' => $client->contacts()->orderBy('last_name')->orderBy('first_name')->get(),
            'can' => $this->contactPermissions(),
        ]);
    }

    public function store(StoreContactRequest $request, Client $client, CreateContact $createContact): RedirectResponse
    {
        $createContact->handle($client, $request->validated());

        return redirect()
            ->route('clients.show', $client)
            ->with('success', __('contacts.created'));
    }

    public function edit(Contact $contact): Response
    {
        $contact->load('client');

        return Inertia::render('Contacts/Edit', [
            'contact' => $contact,
            'client' => $contact->client,
            'contacts' => $contact->client->contacts()
                ->whereKeyNot($contact->getKey())
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'can' => $this->contactPermissions(),
        ]);
    }

    /**
     * Permisos que no dependen de un contacto concreto.
     *
     * Se preguntan por nombre y no por la policy: ContactPolicy::update y
     * ::delete exigen un Contact, así que "can('update', Contact::class)"
     * llegaría sin el argumento y fallaría. Es la forma que el Gate siempre
     * puede responder y la que ya comparte HandleInertiaRequests.
     *
     * @return array<string, bool>
     */
    private function contactPermissions(): array
    {
        $user = auth()->user();

        return [
            'create' => $user?->can('contacts.create') ?? false,
            'update' => $user?->can('contacts.update') ?? false,
            'delete' => $user?->can('contacts.delete') ?? false,
            'setPrimary' => $user?->can('contacts.set_primary') ?? false,
        ];
    }

    public function update(UpdateContactRequest $request, Contact $contact, UpdateContact $updateContact): RedirectResponse
    {
        $updateContact->handle($contact, $request->validated());

        return redirect()
            ->route('clients.show', $contact->client)
            ->with('success', __('contacts.updated'));
    }

    public function destroy(Contact $contact, DeleteContact $deleteContact): RedirectResponse
    {
        // El cliente se guarda antes de borrar: después la relación ya no
        // existe y no habría a dónde redirigir.
        $client = $contact->client;

        try {
            $deleteContact->handle($contact);
        } catch (DomainException $exception) {
            // Un contacto con cotización no se borra: es la persona a la que se
            // le envió la propuesta.
            return redirect()
                ->route('clients.show', $client)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('clients.show', $client)
            ->with('success', __('contacts.deleted'));
    }
}
