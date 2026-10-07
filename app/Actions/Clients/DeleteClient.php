<?php

namespace App\Actions\Clients;

use App\Models\Client;
use DomainException;

class DeleteClient
{
    /**
     * No se borra si tiene contenido: nada desaparece en cascada y borrar el
     * cliente destruiría el historial; para eso está inactivarlo.
     *
     * @throws DomainException
     */
    public function handle(Client $client): void
    {
        if ($client->contacts()->exists()) {
            throw new DomainException(__('clients.has_contacts'));
        }

        // Ya hay historial comercial; para eso está inactivarlo.
        if ($client->opportunities()->exists()) {
            throw new DomainException(__('clients.has_opportunities'));
        }

        // Las actividades también forman parte del historial.
        if ($client->activities()->exists()) {
            throw new DomainException(__('clients.has_activities'));
        }

        // La cotización ya fue recibida por el cliente: es un documento con
        // folio propio que no se borra junto con él.
        if ($client->quotes()->exists()) {
            throw new DomainException(__('clients.has_quotes'));
        }

        $client->delete();
    }
}
