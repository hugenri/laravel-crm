<?php

namespace App\Actions\Prospects;

use App\Models\Prospect;
use DomainException;

class DeleteProspect
{
    /**
     * Un prospecto convertido no se borra: es la traza del origen, y sin ella
     * el embudo no dejaria constancia de quien entro a la cartera. Uno sin
     * convertir si se puede eliminar.
     *
     * @throws DomainException
     */
    public function handle(Prospect $prospect): void
    {
        if ($prospect->isConverted()) {
            throw new DomainException(__('prospects.converted_not_deletable'));
        }

        $prospect->delete();
    }
}
