<?php

namespace App\Http\Requests\Contacts;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;

class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->can('update', $this->route('contact'))) {
            return false;
        }

        if ($this->has('is_primary')) {
            return $user->can('contacts.set_primary');
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:150'],
            'last_name' => ['nullable', 'string', 'max:150'],
            'position' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => __('validation.required', [
                'attribute' => __('contacts.first_name'),
            ]),
        ];
    }
}
