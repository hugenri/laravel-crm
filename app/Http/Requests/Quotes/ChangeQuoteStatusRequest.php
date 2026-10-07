<?php

namespace App\Http\Requests\Quotes;

use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeQuoteStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quote = $this->route('quote');

        return $quote instanceof Quote
            && ($this->user()?->can('changeStatus', $quote) ?? false);
    }

    /**
     * El estado viene en la ruta; se añade a validationData para validarlo.
     */
    public function validationData(): array
    {
        return array_merge(parent::validationData(), [
            'status' => $this->route('status'),
        ]);
    }

    /**
     * Valida que el estado exista en el catálogo.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(config('quotes.statuses')))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => __('validation.in', ['attribute' => __('quotes.status')]),
        ];
    }
}
