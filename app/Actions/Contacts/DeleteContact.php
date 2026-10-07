<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use DomainException;

/**
 * Borrado de un contacto.
 *
 * Si se borra el principal no se promueve a otro: la regla es "como máximo
 * uno", no exactamente uno.
 *
 * Tampoco se borra si recibió cotizaciones: es la persona a la que se le envió
 * la propuesta y se perdería ese vínculo.
 */
class DeleteContact
{
    /**
     * @throws DomainException
     */
    public function handle(Contact $contact): void
    {
        if ($contact->quotes()->exists()) {
            throw new DomainException(__('contacts.has_quotes'));
        }

        $contact->delete();
    }
}
