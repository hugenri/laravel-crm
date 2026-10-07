<?php

namespace App\Http\Requests\Opportunities;

use App\Models\Opportunity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas comunes para crear y editar oportunidades.
 */
abstract class OpportunityRequest extends FormRequest
{
    /**
     * @return list<string>
     */
    protected function allowedStages(): array
    {
        return config('opportunities.open_stages');
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('amount')) {
            return;
        }

        $amount = trim((string) $this->input('amount'));

        if ($amount === '') {
            return;
        }

        $this->merge([
            'amount' => str_replace([',', ' ', "\u{00A0}"], '', $amount),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.config('opportunities.name_max')],
            'client_id' => ['required', 'integer', 'exists:clients,id'],

            'contact_id' => [
                'nullable',
                'integer',
                Rule::exists('contacts', 'id')->where(
                    fn ($query) => $query->where('client_id', $this->selectedClientId())
                ),
            ],

            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('is_active', true)
                ),
            ],

            'amount' => [
                'nullable',
                'numeric',
                'min:0',
                'max:'.config('opportunities.amount_max'),
            ],

            'stage' => ['required', Rule::in($this->allowedStages())],

            'status' => ['prohibited'],
            'expected_close_date' => [
                Rule::requiredIf(in_array($this->input('stage'), $this->allowedStages(), true)),
                'nullable',
                'date',
            ],

            'notes' => ['nullable', 'string', 'max:'.config('opportunities.notes_max')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('validation.required', [
                'attribute' => __('opportunities.name'),
            ]),
            'client_id.required' => __('opportunities.client_required'),
            'client_id.exists' => __('validation.exists', [
                'attribute' => __('opportunities.client'),
            ]),
            'contact_id.exists' => __('opportunities.contact_of_another_client'),
            'user_id.required' => __('validation.required', [
                'attribute' => __('opportunities.user'),
            ]),
            'user_id.exists' => __('opportunities.user_inactive'),
            'amount.numeric' => __('validation.numeric', [
                'attribute' => __('opportunities.amount'),
            ]),
            'amount.min' => __('validation.min', [
                'attribute' => __('opportunities.amount'),
            ]),
            'amount.max' => __('validation.max', [
                'attribute' => __('opportunities.amount'),
            ]),
            'stage.in' => __('validation.in', [
                'attribute' => __('opportunities.stage'),
            ]),
            'status.prohibited' => __('opportunities.status_not_editable'),
            'expected_close_date.required' => __('validation.required', [
                'attribute' => __('opportunities.expected_close_date'),
            ]),
            'expected_close_date.date' => __('validation.date', [
                'attribute' => __('opportunities.expected_close_date'),
            ]),
            'notes.max' => __('validation.max', [
                'attribute' => __('opportunities.notes'),
            ]),
        ];
    }

    protected function selectedClientId(): ?int
    {
        $value = $this->input('client_id');

        return $value === null || $value === '' ? null : (int) $value;
    }

    protected function opportunity(): ?Opportunity
    {
        $opportunity = $this->route('opportunity');

        return $opportunity instanceof Opportunity ? $opportunity : null;
    }
}
