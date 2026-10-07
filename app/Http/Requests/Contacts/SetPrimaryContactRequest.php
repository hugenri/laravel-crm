<?php

namespace App\Http\Requests\Contacts;

use Illuminate\Foundation\Http\FormRequest;

class SetPrimaryContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('setPrimary', $this->route('contact')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
