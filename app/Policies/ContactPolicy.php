<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;

/**
 * Autorización del módulo de contactos.
 *
 * Los contactos viven dentro de un cliente, pero sus habilidades revisan los
 * permisos de contacto, no los que el usuario tenga en el cliente padre.
 * contacts.set_primary es distinto de contacts.update: así un vendedor puede
 * editar un contacto sin decidir quién es el principal.
 */
class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('contacts.view');
    }

    public function view(User $user, Contact $contact): bool
    {
        return $user->can('contacts.view');
    }

    public function create(User $user, Client $client): bool
    {
        return $user->can('contacts.create');
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->can('contacts.update');
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $user->can('contacts.delete');
    }

    /**
     * Marcar el principal cambia a quién contacta el equipo comercial, así que
     * es su propio permiso.
     */
    public function setPrimary(User $user, Contact $contact): bool
    {
        return $user->can('contacts.set_primary');
    }
}
