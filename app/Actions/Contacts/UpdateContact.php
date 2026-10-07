<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use Illuminate\Support\Facades\DB;

class UpdateContact
{
    public function __construct(private readonly SetPrimaryContact $setPrimaryContact) {}

    /**
     * Cada campo se escribe solo si llegó en la petición. El cliente no se
     * puede cambiar aquí: mover un contacto a otra empresa es una operación
     * distinta y no forma parte de esta fase.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Contact $contact, array $data): Contact
    {
        return DB::transaction(function () use ($contact, $data): Contact {
            if (array_key_exists('first_name', $data)) {
                $contact->first_name = trim($data['first_name']);
            }

            if (array_key_exists('last_name', $data)) {
                $contact->last_name = $data['last_name'];
            }

            if (array_key_exists('position', $data)) {
                $contact->position = $data['position'];
            }

            if (array_key_exists('phone', $data)) {
                $contact->phone = $data['phone'];
            }

            if (array_key_exists('email', $data)) {
                $email = mb_strtolower(trim((string) $data['email']));
                $contact->email = $email === '' ? null : $email;
            }

            if (array_key_exists('notes', $data)) {
                $contact->notes = $data['notes'];
            }

            $contact->save();

            // Desmarcar también degrada al principal; si no, un cliente nunca
            // podría quedarse sin uno. Promover tiene su propio permiso.
            if (array_key_exists('is_primary', $data)) {
                filter_var($data['is_primary'], FILTER_VALIDATE_BOOLEAN)
                    ? $this->setPrimaryContact->handle($contact)
                    : $contact->forceFill(['is_primary' => false])->save();
            }

            return $contact->refresh();
        });
    }
}
