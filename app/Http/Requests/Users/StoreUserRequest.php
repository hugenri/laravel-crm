<?php

namespace App\Http\Requests\Users;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        $actor = $this->user();

        if ($actor === null || ! $actor->can('create', User::class)) {
            return false;
        }

        if ($this->has('roles')) {
            return $actor->can('assignRoles', User::class);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $roleIds = Role::query()->pluck('id')->all();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => $this->passwordRules(),
            'is_active' => ['required', 'boolean'],
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
            'is_active.required' => __('validation.required', ['attribute' => __('users.status')]),
            'roles.*.in' => __('roles.invalid_role'),
        ];
    }
}
