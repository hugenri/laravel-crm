<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

/**
 * Autorización del módulo de actividades.
 *
 * Como en los demás módulos, cada habilidad es solo una comprobación de
 * permiso: el alcance aplaza propiedad y visibilidad, así que activities.view
 * alcanza al listado completo.
 */
class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('activities.view');
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->can('activities.view');
    }

    public function create(User $user): bool
    {
        return $user->can('activities.create');
    }

    public function update(User $user, Activity $activity): bool
    {
        return $user->can('activities.update');
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $user->can('activities.delete');
    }
}
