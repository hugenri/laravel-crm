<?php

namespace App\Actions\Users;

use App\Models\User;
use DomainException;

class DeleteUser
{
    /**
     * @throws DomainException
     */
    public function handle(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw new DomainException(__('users.cannot_delete_self'));
        }

        if ($target->isActive() && User::activeCount() <= 1) {
            throw new DomainException(__('users.last_active_user'));
        }

        $target->delete();
    }
}
