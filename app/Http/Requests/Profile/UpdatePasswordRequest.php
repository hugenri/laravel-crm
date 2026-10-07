<?php

namespace App\Http\Requests\Profile;

use App\Actions\Fortify\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' => __('validation.current_password'),
        ];
    }

    /**
     * @return array{password: string, password_confirmation: string}
     */
    public function passwordData(): array
    {
        return [
            'password' => (string) $this->validated('password'),
            'password_confirmation' => (string) $this->input('password_confirmation'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('password_confirmation')) {
            $this->merge(['password_confirmation' => $this->input('password')]);
        }
    }
}
