<?php

namespace App\Http\Requests\Clients;

use App\Models\Client;
use App\Rules\Rfc;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('client')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Client $client */
        $client = $this->route('client');

        return [
            'type' => ['required', Rule::in(array_keys(config('clients.types')))],
            'name' => ['required', 'string', 'max:255'],

            'rfc' => [
                'nullable',
                'string',
                'max:13',
                new Rfc($this->input('type', $client->type)),

                // Ignorar al cliente editado para evitar colisión al guardar sin cambiar RFC.
                Rule::unique('clients', 'rfc')->ignore($client),
            ],

            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:5000'],

            // El estatus no se modifica aquí; se usa ChangeClientStatus para proteger permiso change_status.
            'status' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rfc.unique' => __('clients.rfc_taken'),
            'rfc.max' => __('validation.max', [
                'attribute' => __('clients.rfc'),
                'max' => 13,
            ]),
            'status.prohibited' => __('clients.status_not_editable'),
        ];
    }
}
