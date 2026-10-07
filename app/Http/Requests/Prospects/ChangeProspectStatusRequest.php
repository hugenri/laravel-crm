<?php

namespace App\Http\Requests\Prospects;

use App\Models\Prospect;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cambio de estado de un prospecto.
 *
 * El estado viene en la ruta; se añade a validationData para validarlo.
 */
class ChangeProspectStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $prospect = $this->route('prospect');

        return $prospect instanceof Prospect
            && ! $prospect->isConverted()
            && ($this->user()?->can('changeStatus', $prospect) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return array_merge(parent::validationData(), [
            'status' => $this->route('status'),
        ]);
    }

    /**
     * Solo permite estados elegibles manualmente; "convertido" no se permite aquí.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Prospect::qualifiedStatuses())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => __('prospects.status_not_selectable'),
        ];
    }
}
