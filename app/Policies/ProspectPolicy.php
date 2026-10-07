<?php

namespace App\Policies;

use App\Models\Prospect;
use App\Models\User;

/**
 * Autorización del módulo de prospectos.
 *
 * Como en los demás módulos, cada habilidad es solo una comprobación de
 * permiso: prospects.view alcanza a todo el pipeline; el filtro por
 * responsable existe para consultar, no para ocultar.
 *
 * Dos habilidades llevan permiso propio:
 *
 *  - changeStatus: calificar o descartar es una decisión comercial.
 *  - convert: crea un cliente y un contacto, y nada en la aplicación lo
 *    deshace.
 */
class ProspectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('prospects.view');
    }

    public function view(User $user, Prospect $prospect): bool
    {
        return $user->can('prospects.view');
    }

    public function create(User $user): bool
    {
        return $user->can('prospects.create');
    }

    public function update(User $user, Prospect $prospect): bool
    {
        return $user->can('prospects.update');
    }

    public function delete(User $user, Prospect $prospect): bool
    {
        return $user->can('prospects.delete');
    }

    /**
     * El formulario normal no ofrece el estado, así que con prospects.update
     * no basta para cerrar el registro; el "convertido" queda para la
     * conversión. Un convertido ya no se mueve, y ChangeProspectStatusRequest
     * repite la comprobación por si la request se salta la policy.
     */
    public function changeStatus(User $user, Prospect $prospect): bool
    {
        return $user->can('prospects.change_status') && ! $prospect->isConverted();
    }

    /**
     * Convierte escribiendo en dos módulos y no se puede deshacer, así que
     * permiso propio y no colgado de prospects.update. Un ya convertido se
     * rechaza aquí, en ConvertProspect (dentro de la transacción) y en
     * ConvertProspectRequest: el permiso dice quién, el estado si queda algo
     * que convertir.
     */
    public function convert(User $user, Prospect $prospect): bool
    {
        return $user->can('prospects.convert') && ! $prospect->isConverted();
    }
}
