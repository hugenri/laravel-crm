<?php

namespace App\Actions\Clients;

use App\Models\Client;
use DomainException;

class ChangeClientStatus
{
    /**
     * Desactivar no es borrar: se conserva el cliente y sus contactos para
     * que el resto de módulos siga leyendo su historial.
     *
     * @throws DomainException
     */
    public function handle(Client $client, string $status): Client
    {
        if (! array_key_exists($status, config('clients.statuses'))) {
            throw new DomainException(__('clients.invalid_status'));
        }

        $client->update(['status' => $status]);

        return $client->refresh();
    }
}
