<?php

namespace App\Http\Requests\Prospects;

use App\Models\Prospect;
use Illuminate\Validation\Rule;

class StoreProspectRequest extends ProspectRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Prospect::class) ?? false;
    }

    /**
     * En alta se permite elegir estado, excepto "convertido".
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'status' => ['required', Rule::in($this->allowedStatuses())],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'status.required' => __('validation.required', [
                'attribute' => __('prospects.status'),
            ]),
            'status.in' => __('prospects.status_not_selectable'),
        ]);
    }
}
