<?php

namespace App\Http\Requests\Clients;

use App\Models\Client;
use App\Rules\Rfc;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Client::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(config('clients.types')))],
            'name' => ['required', 'string', 'max:255'],

            // El RFC se valida según el tipo; para empresa no se acepta RFC de 13 caracteres.
            // Se mantiene opcional para registrar clientes sin RFC.
            'rfc' => [
                'nullable',
                'string',
                'max:13',
                new Rfc($this->input('type', 'empresa')),
                Rule::unique('clients', 'rfc'),
            ],

            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(array_keys(config('clients.statuses')))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => __('validation.required', ['attribute' => __('clients.type')]),
            'type.in' => __('validation.in', ['attribute' => __('clients.type')]),
            'name.required' => __('validation.required', ['attribute' => __('clients.name')]),
            'status.required' => __('validation.required', ['attribute' => __('clients.status')]),
            'status.in' => __('validation.in', ['attribute' => __('clients.status')]),
            'rfc.unique' => __('clients.rfc_taken'),
            'rfc.max' => __('validation.max', [
                'attribute' => __('clients.rfc'),
                'max' => 13,
            ]),
        ];
    }
}
