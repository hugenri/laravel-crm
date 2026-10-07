<?php

namespace App\Http\Requests\Prospects;

use App\Models\Prospect;
use App\Rules\Rfc;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Convierte un prospecto en cliente con su contacto.
 */
class ConvertProspectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $prospect = $this->route('prospect');

        return $prospect instanceof Prospect
            && ! $prospect->isConverted()
            && ($this->user()?->can('convert', $prospect) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // --- Cliente ---
            'client_name' => ['required', 'string', 'max:255'],
            'client_type' => ['required', Rule::in(array_keys(config('clients.types')))],

            'rfc' => [
                'nullable',
                'string',
                'max:13',
                new Rfc($this->input('client_type', 'empresa')),
                Rule::unique('clients', 'rfc'),
            ],

            'address' => ['nullable', 'string', 'max:1000'],
            'client_notes' => ['nullable', 'string', 'max:5000'],

            // --- Contacto ---
            'first_name' => ['required', 'string', 'max:150'],
            'last_name' => ['nullable', 'string', 'max:150'],
            'position' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_name.required' => __('validation.required', [
                'attribute' => __('clients.name'),
            ]),
            'client_type.in' => __('validation.in', ['attribute' => __('clients.type')]),
            'rfc.unique' => __('clients.rfc_taken'),
            'rfc.max' => __('validation.max', [
                'attribute' => __('clients.rfc'),
                'max' => 13,
            ]),
            'first_name.required' => __('validation.required', [
                'attribute' => __('contacts.first_name'),
            ]),
        ];
    }
}
