<?php

namespace App\Actions\Opportunities;

use App\Models\Opportunity;
use DomainException;

/**
 * Borrado de una oportunidad. Solo borra su propia fila: no toca el cliente,
 * el contacto ni el responsable.
 *
 * No se borra si tiene actividades o cotizaciones, para no romper el historial.
 */
class DeleteOpportunity
{
    /**
     * @throws DomainException
     */
    public function handle(Opportunity $opportunity): void
    {
        if ($opportunity->activities()->exists()) {
            throw new DomainException(__('opportunities.has_activities'));
        }

        if ($opportunity->quotes()->exists()) {
            throw new DomainException(__('opportunities.has_quotes'));
        }

        $opportunity->delete();
    }
}
