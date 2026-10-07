<?php

namespace App\Http\Controllers\Users;

use App\Actions\Users\ChangeUserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserStatusController extends Controller
{
    public function __construct(private readonly ChangeUserStatus $changeUserStatus) {}

    /**
     * Activa o desactiva una cuenta. El estado deseado va en la URL y no como
     * interruptor, para que la petición sea idempotente y no se pueda repetir
     * quedando en el estado contrario.
     */
    public function __invoke(Request $request, User $user, string $status): RedirectResponse
    {
        if (! in_array($status, ['activate', 'deactivate'], true)) {
            return back()->with('error', __('users.invalid_status'));
        }

        $isActive = $status === 'activate';

        try {
            $this->changeUserStatus->handle($request->user(), $user, $isActive);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $isActive
            ? __('users.activated', ['name' => $user->name])
            : __('users.deactivated', ['name' => $user->name]));
    }
}
