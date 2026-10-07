<?php

namespace App\Actions\Users;

use App\Models\User;
use DomainException;

class ChangeUserStatus
{
    /**
     * Alterna el estado de la cuenta. Las reglas que evitan quedarse sin
     * administradores viven aqui y no en el controlador, para que tambien
     * apliquen a futuras entradas como una API.
     *
     * @throws DomainException
     */
    public function handle(User $actor, User $target, bool $isActive): User
    {
        if ($actor->is($target)) {
            throw new DomainException(__('users.cannot_change_own_status'));
        }

        if (! $isActive && $target->isActive() && User::activeCount() <= 1) {
            throw new DomainException(__('users.last_active_user'));
        }

        $target->update(['is_active' => $isActive]);

        return $target->refresh();
    }
}
