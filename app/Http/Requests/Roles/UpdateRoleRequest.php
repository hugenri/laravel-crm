<?php

namespace App\Http\Requests\Roles;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('role')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        $name = [
            'required',
            'string',
            'max:255',
            Rule::unique('roles', 'name')
                ->where(fn ($query) => $query->where('guard_name', 'web'))
                ->ignore($role->id),
        ];

        if ($role->isSystemRole()) {
            $name[] = Rule::in([$role->name]);
        }

        return [
            'name' => $name,
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('validation.required', ['attribute' => __('roles.role_name')]),
            'name.in' => __('roles.system_role_name_locked'),
        ];
    }
}
