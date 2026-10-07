<?php

namespace App\Http\Controllers\Contacts;

use App\Actions\Contacts\SetPrimaryContact as SetPrimaryContactAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contacts\SetPrimaryContactRequest;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;

class PrimaryContactController extends Controller
{
    /**
     * Marca un contacto como principal. La regla de "un solo principal" vive
     * en App\Actions\Contacts\SetPrimaryContact.
     */
    public function __invoke(
        SetPrimaryContactRequest $request,
        Contact $contact,
        SetPrimaryContactAction $setPrimaryContact,
    ): RedirectResponse {
        $client = $contact->client;

        $setPrimaryContact->handle($contact);

        return redirect()
            ->route('clients.show', $client)
            ->with('success', __('contacts.primary_updated'));
    }
}
