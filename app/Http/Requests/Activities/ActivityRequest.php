<?php

namespace App\Http\Requests\Activities;

use App\Models\Activity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas comunes para crear y editar actividades.
 *
 * Garantiza que la oportunidad pertenezca al cliente seleccionado,
 * aunque el formulario ya filtre esa relación.
 */
abstract class ActivityRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(config('activities.types')))],

            'client_id' => ['required', 'integer', 'exists:clients,id'],

            // La oportunidad debe pertenecer al cliente seleccionado.
            'opportunity_id' => [
                'nullable',
                'integer',
                Rule::exists('opportunities', 'id')->where(
                    fn ($query) => $query->where('client_id', $this->selectedClientId())
                ),
            ],

            // El responsable debe existir y estar activo.
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('is_active', true)
                ),
            ],

            'activity_date' => ['required', 'date'],

            'status' => ['required', Rule::in(array_keys(config('activities.statuses')))],

            'description' => ['required', 'string', 'max:'.config('activities.description_max')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => __('validation.required', [
                'attribute' => __('activities.type'),
            ]),
            'type.in' => __('validation.in', [
                'attribute' => __('activities.type'),
            ]),
            'client_id.required' => __('activities.client_required'),
            'client_id.exists' => __('validation.exists', [
                'attribute' => __('activities.client'),
            ]),
            'opportunity_id.exists' => __('activities.opportunity_of_another_client'),
            'user_id.required' => __('validation.required', [
                'attribute' => __('activities.user'),
            ]),
            'user_id.exists' => __('activities.user_inactive'),
            'activity_date.required' => __('validation.required', [
                'attribute' => __('activities.activity_date'),
            ]),
            'activity_date.date' => __('validation.date', [
                'attribute' => __('activities.activity_date'),
            ]),
            'status.required' => __('validation.required', [
                'attribute' => __('activities.status'),
            ]),
            'status.in' => __('validation.in', [
                'attribute' => __('activities.status'),
            ]),
            'description.required' => __('validation.required', [
                'attribute' => __('activities.description'),
            ]),
            'description.max' => __('validation.max', [
                'attribute' => __('activities.description'),
            ]),
        ];
    }

    /**
     * Obtiene client_id ya convertido a int para validar oportunidad.
     */
    protected function selectedClientId(): ?int
    {
        $value = $this->input('client_id');

        return $value === null || $value === '' ? null : (int) $value;
    }

    /**
     * Devuelve la actividad de la ruta (solo para edición).
     */
    protected function activity(): ?Activity
    {
        $activity = $this->route('activity');

        return $activity instanceof Activity ? $activity : null;
    }
}
