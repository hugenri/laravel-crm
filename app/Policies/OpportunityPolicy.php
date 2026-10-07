<?php

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\User;

/**
 * Autorización del módulo de oportunidades.
 *
 * Cada habilidad es solo una comprobación de permiso: el alcance aplaza
 * propiedad, territorio y el resto de visibilidad, así que opportunities.view
 * implica ver todo el listado, no solo las del responsable.
 */
class OpportunityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('opportunities.view');
    }

    public function view(User $user, Opportunity $opportunity): bool
    {
        return $user->can('opportunities.view');
    }

    public function create(User $user): bool
    {
        return $user->can('opportunities.create');
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        return $user->can('opportunities.update');
    }

    public function delete(User $user, Opportunity $opportunity): bool
    {
        return $user->can('opportunities.delete');
    }

    /**
     * Permiso aparte de editar: el formulario normal solo ofrece las etapas
     * abiertas, así que con opportunities.update no basta para terminar un
     * trato. Además, perderlo es una decisión comercial que un supervisor
     * puede querer controlar.
     */
    public function changeStatus(User $user, Opportunity $opportunity): bool
    {
        return $user->can('opportunities.change_status');
    }
}
