<?php

namespace App\Http\Requests\Users;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        $actor = $this->user();

        if ($actor === null) {
            return false;
        }

        /** @var User $user */
        $user = $this->route('user');

        if (! $actor->can('update', $user)) {
            return false;
        }

        if ($this->has('roles') && ! $actor->can('assignRoles', $user)) {
            return false;
        }

        if ($this->has('is_active') && ! $actor->can('changeStatus', $user)) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        $roleIds = Role::query()->pluck('id')->all();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'confirmed', Password::default()],
            'is_active' => ['sometimes', 'boolean'],
            'email_verified' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['integer', Rule::in($roleIds)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'roles.*.in' => __('roles.invalid_role'),
        ];
    }
}
