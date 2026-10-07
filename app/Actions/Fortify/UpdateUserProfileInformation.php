<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

/**
 * Guarda los cambios del perfil. La validación vive en el form request que
 * llama a esta acción, para no repetir las reglas.
 */
class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * @param  array{name: string, email: string}  $input
     */
    public function update(User $user, array $input): void
    {
        $email = mb_strtolower($input['email']);

        $user->forceFill([
            'name' => $input['name'],
            'email' => $email,
        ]);

        // Cambiar el email invalida la verificación anterior: la cuenta vuelve
        // al aviso de verificar el correo.
        if ($email !== $user->getOriginal('email') && $user instanceof MustVerifyEmail) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($user->wasChanged('email') && $user instanceof MustVerifyEmail) {
            $user->sendEmailVerificationNotification();
        }
    }
}
