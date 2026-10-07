<?php

namespace App\Providers;

use App\Actions\Fortify\AuthenticateUser;
use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\LogoutResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LogoutResponseContract::class, LogoutResponse::class);

        // Se ejecuta antes de que arranque cualquier provider, que es cuando
        // Fortify lee su lista de features para decidir si registra las rutas
        // de registro.
        if (! config('crm.registration_enabled')) {
            config(['fortify.features' => array_values(array_filter(
                config('fortify.features', []),
                static fn (array $feature): bool => $feature['name'] !== Features::registration()['name'],
            ))]);
        }
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Pipeline de login propia para rechazar cuentas desactivadas antes de
        // que exista sesión. El throttling sigue en el middleware de la ruta,
        // por eso aquí no se repite EnsureLoginIsNotThrottled.
        Fortify::loginThrough(fn (Request $request) => [
            CanonicalizeUsername::class,
            AuthenticateUser::class,
            PrepareAuthenticatedSession::class,
        ]);

        Fortify::loginView(fn () => Inertia::render('Auth/Login'));

        Fortify::registerView(fn () => Inertia::render('Auth/Register'));

        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('Auth/ForgotPassword'));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('Auth/ResetPassword', [
            'email' => $request->input('email'),
            'token' => $request->route('token'),
        ]));

        Fortify::verifyEmailView(fn () => Inertia::render('Auth/VerifyEmail', [
            'status' => session('status'),
        ]));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
