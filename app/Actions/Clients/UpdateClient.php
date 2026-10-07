<?php

namespace App\Actions\Clients;

use App\Models\Client;
use Illuminate\Support\Str;

class UpdateClient
{
    /**
     * Cada campo se escribe solo si llegó en la petición. El status no está
     * aquí a propósito: cambiarlo es tarea de ChangeClientStatus.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Client $client, array $data): Client
    {
        if (array_key_exists('name', $data)) {
            $client->name = trim($data['name']);
        }

        if (array_key_exists('type', $data)) {
            $client->type = $data['type'];
        }

        if (array_key_exists('rfc', $data)) {
            $rfc = Str::upper(trim((string) $data['rfc']));

            $client->rfc = $rfc === '' ? null : $rfc;
        }

        if (array_key_exists('phone', $data)) {
            $client->phone = $data['phone'];
        }

        if (array_key_exists('email', $data)) {
            $email = mb_strtolower(trim((string) $data['email']));

            $client->email = $email === '' ? null : $email;
        }

        if (array_key_exists('address', $data)) {
            $client->address = $data['address'];
        }

        if (array_key_exists('notes', $data)) {
            $client->notes = $data['notes'];
        }

        $client->save();

        return $client->refresh();
    }
}
