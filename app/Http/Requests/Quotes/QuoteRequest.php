<?php

namespace App\Http\Requests\Quotes;

use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas comunes para crear y editar cotizaciones.
 */
abstract class QuoteRequest extends FormRequest
{
    /**
     * Normaliza números con separador de miles antes de validar.
     */
    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (! is_array($items)) {
            return;
        }

        $cleaned = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                $cleaned[$index] = $item;

                continue;
            }

            $cleaned[$index] = array_merge($item, array_filter([
                'quantity' => $this->normalizeNumber($item['quantity'] ?? null),
                'unit_price' => $this->normalizeNumber($item['unit_price'] ?? null),
            ], fn (mixed $value): bool => $value !== null));
        }

        $this->merge([
            'items' => $cleaned,
            'tax_rate' => $this->normalizeNumber($this->input('tax_rate')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],

            'contact_id' => [
                'nullable',
                'integer',
                Rule::exists('contacts', 'id')->where(
                    fn ($query) => $query->where('client_id', $this->selectedClientId())
                ),
            ],

            'opportunity_id' => [
                'nullable',
                'integer',
                Rule::exists('opportunities', 'id')->where(
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

            'issue_date' => ['required', 'date', 'before_or_equal:today'],

            'valid_until' => ['required', 'date', 'after_or_equal:issue_date'],

            'notes' => ['nullable', 'string', 'max:'.config('quotes.notes_max')],

            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:'.config('quotes.tax_rate_max')],

            'folio' => ['prohibited'],
            'status' => ['prohibited'],
            'subtotal' => ['prohibited'],
            'tax_amount' => ['prohibited'],
            'total' => ['prohibited'],

            'items' => ['required', 'array', 'min:'.config('quotes.items_min'), 'max:'.config('quotes.items_max')],
            'items.*.description' => ['required', 'string', 'max:'.config('quotes.description_max')],
            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
                'max:'.config('quotes.quantity_max'),
            ],
            'items.*.unit_price' => [
                'required',
                'numeric',
                'min:0',
                'max:'.config('quotes.unit_price_max'),
            ],

            'items.*.amount' => ['prohibited'],
            'items.*.position' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_id.required' => __('quotes.client_required'),
            'client_id.exists' => __('validation.exists', ['attribute' => __('quotes.client')]),
            'contact_id.exists' => __('quotes.contact_of_another_client'),
            'opportunity_id.exists' => __('quotes.opportunity_of_another_client'),
            'user_id.required' => __('validation.required', ['attribute' => __('quotes.user')]),
            'user_id.exists' => __('quotes.user_inactive'),
            'issue_date.required' => __('validation.required', ['attribute' => __('quotes.issue_date')]),
            'issue_date.date' => __('validation.date', ['attribute' => __('quotes.issue_date')]),
            'issue_date.before_or_equal' => __('quotes.issue_date_not_future'),
            'valid_until.required' => __('validation.required', ['attribute' => __('quotes.valid_until')]),
            'valid_until.date' => __('validation.date', ['attribute' => __('quotes.valid_until')]),
            'valid_until.after_or_equal' => __('quotes.valid_until_before_issue'),
            'notes.max' => __('validation.max', ['attribute' => __('quotes.notes')]),

            'tax_rate.numeric' => __('validation.numeric', ['attribute' => __('quotes.tax_rate')]),
            'tax_rate.min' => __('validation.min', ['attribute' => __('quotes.tax_rate')]),
            'tax_rate.max' => __('validation.max', ['attribute' => __('quotes.tax_rate')]),

            'folio.prohibited' => __('quotes.folio_not_editable'),
            'status.prohibited' => __('quotes.status_not_editable'),
            'subtotal.prohibited' => __('quotes.totals_not_editable'),
            'tax_amount.prohibited' => __('quotes.totals_not_editable'),
            'total.prohibited' => __('quotes.totals_not_editable'),

            'items.required' => __('quotes.items_required'),
            'items.array' => __('quotes.items_required'),
            'items.min' => __('quotes.items_required'),
            'items.max' => __('validation.max', ['attribute' => __('quotes.items')]),
            'items.*.description.required' => __('validation.required', ['attribute' => __('quotes.description')]),
            'items.*.description.max' => __('validation.max', ['attribute' => __('quotes.description')]),
            'items.*.quantity.required' => __('validation.required', ['attribute' => __('quotes.quantity')]),
            'items.*.quantity.numeric' => __('validation.numeric', ['attribute' => __('quotes.quantity')]),
            'items.*.quantity.gt' => __('quotes.quantity_must_be_positive'),
            'items.*.quantity.max' => __('validation.max', ['attribute' => __('quotes.quantity')]),
            'items.*.unit_price.required' => __('validation.required', ['attribute' => __('quotes.unit_price')]),
            'items.*.unit_price.numeric' => __('validation.numeric', ['attribute' => __('quotes.unit_price')]),
            'items.*.unit_price.min' => __('validation.min', ['attribute' => __('quotes.unit_price')]),
            'items.*.unit_price.max' => __('validation.max', ['attribute' => __('quotes.unit_price')]),
            'items.*.amount.prohibited' => __('quotes.item_amount_not_editable'),
            'items.*.position.prohibited' => __('quotes.item_position_not_editable'),
        ];
    }

    /**
     * Normaliza número: elimina separadores de miles, devuelve null si vacío.
     */
    private function normalizeNumber(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return str_replace([',', ' ', "\u{00A0}"], '', trim((string) $value));
    }

    /**
     * Obtiene client_id ya convertido a int para validar relaciones.
     */
    protected function selectedClientId(): ?int
    {
        $value = $this->input('client_id');

        return $value === null || $value === '' ? null : (int) $value;
    }

    /**
     * Devuelve la cotización de la ruta (solo para edición).
     */
    protected function quote(): ?Quote
    {
        $quote = $this->route('quote');

        return $quote instanceof Quote ? $quote : null;
    }
}
