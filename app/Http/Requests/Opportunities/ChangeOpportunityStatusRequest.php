<?php

namespace App\Http\Requests\Opportunities;

use App\Models\Opportunity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeOpportunityStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $opportunity = $this->route('opportunity');

        return $opportunity instanceof Opportunity
            && ($this->user()?->can('changeStatus', $opportunity) ?? false);
    }

    public function validationData(): array
    {
        return array_merge(parent::validationData(), [
            'status' => $this->route('status'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(config('opportunities.statuses')))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => __('validation.in', ['attribute' => __('opportunities.status')]),
        ];
    }
}
