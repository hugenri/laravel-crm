<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Closure;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Validated;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;

/**
 * Reemplaza a AttemptToAuthenticate de Fortify: rechaza cuentas desactivadas
 * antes de crear la sesión y explica el motivo.
 */
class AuthenticateUser
{
    public function __construct(
        private readonly StatefulGuard $guard,
        private readonly LoginRateLimiter $limiter,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $email = (string) $request->input(Fortify::username());
        $user = User::query()->where('email', mb_strtolower($email))->first();

        if ($user === null || ! Hash::check((string) $request->input('password'), $user->password)) {
            $this->reject($request, __('auth.failed'));
        }

        if (! $user->isActive()) {
            $this->reject($request, __('auth.account_inactive'));
        }

        $this->guard->login($user, $request->boolean('remember'));

        event(new Validated($this->guard, $user));

        return $next($request);
    }

    /**
     * Contar el intento en el limitador y lanzar el error.
     *
     * @throws ValidationException
     */
    private function reject(Request $request, string $message): never
    {
        $this->limiter->increment($request);

        event(new Failed($this->guard->name, null, [
            Fortify::username() => $request->input(Fortify::username()),
            'password' => $request->input('password'),
        ]));

        throw ValidationException::withMessages([
            Fortify::username() => [$message],
        ]);
    }
}
