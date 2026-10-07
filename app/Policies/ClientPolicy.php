<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

/**
 * Autorización del módulo de clientes.
 *
 * Solo responde si el permiso existe. Las reglas de datos, como no borrar un
 * cliente que aún tiene contactos, viven en App\Actions\Clients\DeleteClient
 * para que apliquen desde cualquier punto de entrada.
 */
class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clients.view');
    }

    public function view(User $user, Client $client): bool
    {
        return $user->can('clients.view');
    }

    public function create(User $user): bool
    {
        return $user->can('clients.create');
    }

    public function update(User $user, Client $client): bool
    {
        return $user->can('clients.update');
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->can('clients.delete');
    }

    public function changeStatus(User $user, Client $client): bool
    {
        return $user->can('clients.change_status');
    }
}
