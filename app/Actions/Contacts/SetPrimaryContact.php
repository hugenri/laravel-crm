<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use Illuminate\Support\Facades\DB;

/**
 * Único lugar donde se aplica que un cliente tiene como máximo un contacto
 * principal.
 *
 * Alta, edición y promoción pasan por aquí. La transacción cubre las dos
 * escrituras y el bloqueo del cliente evita promociones simultáneas.
 */
class SetPrimaryContact
{
    public function handle(Contact $contact): Contact
    {
        DB::transaction(function () use ($contact): void {
            // El bloqueo serializa promociones simultáneas sobre el mismo
            // cliente; sin él, la transacción solo da atomicidad. SQLite
            // ignora el lock, así que los tests se comportan igual.
            $client = $contact->client()->lockForUpdate()->firstOrFail();

            $client->contacts()
                ->whereKeyNot($contact->getKey())
                ->where('is_primary', true)
                ->update(['is_primary' => false]);

            $contact->forceFill(['is_primary' => true])->save();
        });

        return $contact->refresh();
    }
}
