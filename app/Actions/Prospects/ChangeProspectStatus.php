<?php

namespace App\Actions\Prospects;

use App\Models\Prospect;
use DomainException;

/**
 * Cambio de estado dentro del embudo. Es la unica via a "calificado" o
 * "no_interesado", y por eso tiene permiso propio (prospects.change_status).
 *
 * Un prospecto convertido queda fuera del ciclo: deshacerlo no esta definido,
 * asi que se rechaza con un mensaje explicito.
 */
class ChangeProspectStatus
{
    public function handle(Prospect $prospect, string $status): Prospect
    {
        if ($prospect->isConverted()) {
            throw new DomainException(__('prospects.already_converted'));
        }

        // El catalogo se vuelve a comprobar aqui: la accion no se fia de quien
        // la llame, y "convertido" solo se alcanza por ConvertProspect.
        if (! in_array($status, config('prospects.qualified_statuses', []), true)) {
            throw new DomainException(__('prospects.status_not_available'));
        }

        $prospect->forceFill([
            'status' => $status,
        ])->save();

        return $prospect->refresh();
    }
}
