<?php

namespace App\Http\Requests\Prospects;

use App\Models\Prospect;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas comunes para crear y editar prospectos.
 *
 * Se usa clase base para compartir validaciones entre alta y edición.
 */
abstract class ProspectRequest extends FormRequest
{
    /**
     * Estados permitidos al crear o editar manualmente.
     *
     * @return list<string>
     */
    protected function allowedStatuses(): array
    {
        return Prospect::qualifiedStatuses();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.config('prospects.name_max')],

            'company' => ['nullable', 'string', 'max:'.config('prospects.company_max')],
            'phone' => ['nullable', 'string', 'max:'.config('prospects.phone_max')],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'source' => ['required', Rule::in(array_keys(config('prospects.sources')))],

            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('is_active', true)
                ),
            ],

            'follow_up_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:'.config('prospects.notes_max')],

            'client_id' => ['prohibited'],
            'contact_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('validation.required', [
                'attribute' => __('prospects.name'),
            ]),
            'company.max' => __('validation.max', [
                'attribute' => __('prospects.company'),
                'max' => config('prospects.company_max'),
            ]),
            'phone.max' => __('validation.max', [
                'attribute' => __('prospects.phone'),
                'max' => config('prospects.phone_max'),
            ]),
            'email.email' => __('validation.email', [
                'attribute' => __('prospects.email'),
            ]),
            'source.in' => __('validation.in', [
                'attribute' => __('prospects.source'),
            ]),
            'user_id.required' => __('validation.required', [
                'attribute' => __('prospects.user'),
            ]),
            'user_id.exists' => __('prospects.user_inactive'),
            'follow_up_date.date' => __('validation.date', [
                'attribute' => __('prospects.follow_up_date'),
            ]),
            'notes.max' => __('validation.max', [
                'attribute' => __('prospects.notes'),
                'max' => config('prospects.notes_max'),
            ]),
            'client_id.prohibited' => __('prospects.conversion_fields'),
            'contact_id.prohibited' => __('prospects.conversion_fields'),
        ];
    }
}
