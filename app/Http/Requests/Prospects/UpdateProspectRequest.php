<?php

namespace App\Http\Requests\Prospects;

use App\Models\Prospect;

class UpdateProspectRequest extends ProspectRequest
{
    public function authorize(): bool
    {
        $prospect = $this->route('prospect');

        return $prospect instanceof Prospect
            && ($this->user()?->can('update', $prospect) ?? false);
    }

    /**
     * El estado no se modifica aquí; se usa ChangeProspectStatus para proteger permiso change_status.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'status' => ['prohibited'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'status.prohibited' => __('prospects.status_not_editable'),
        ]);
    }
}
