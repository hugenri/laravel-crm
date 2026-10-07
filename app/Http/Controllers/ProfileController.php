<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private readonly UpdateUserProfileInformation $updateProfileInformation,
        private readonly UpdateUserPassword $updateUserPassword,
    ) {}

    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $this->updateProfileInformation->update(
            $request->user(),
            $request->safe()->all(['name', 'email']),
        );

        return back()->with('success', __('users.profile_updated'));
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $this->updateUserPassword->update($request->user(), $request->passwordData());

        return back()->with('success', __('users.password_updated'));
    }
}
