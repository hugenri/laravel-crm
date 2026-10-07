<?php

namespace App\Actions\Contacts;

use App\Models\Client;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;

class CreateContact
{
    public function __construct(private readonly SetPrimaryContact $setPrimaryContact) {}

    /**
     * El cliente viene de la URL y no del formulario, para que una petición
     * manipulada no cuelgue el contacto en otro cliente.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Client $client, array $data): Contact
    {
        return DB::transaction(function () use ($client, $data): Contact {
            // Se crea sin principal a propósito: la promoción pasa por
            // SetPrimaryContact para aplicar la regla en un solo lugar.
            $contact = $client->contacts()->create([
                'first_name' => trim($data['first_name']),
                'last_name' => $data['last_name'] ?? null,
                'position' => $data['position'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $this->email($data['email'] ?? null),
                'is_primary' => false,
                'notes' => $data['notes'] ?? null,
            ]);

            // filter_var y no filled(): filled(false) es true y una casilla
            // desmarcada promovería el contacto.
            if (filter_var($data['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $this->setPrimaryContact->handle($contact);
            }

            return $contact->refresh();
        });
    }

    private function email(?string $value): ?string
    {
        $value = mb_strtolower(trim((string) $value));

        return $value === '' ? null : $value;
    }
}
