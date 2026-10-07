<?php

namespace App\Http\Requests\Opportunities;

class UpdateOpportunityRequest extends OpportunityRequest
{
    public function authorize(): bool
    {
        $opportunity = $this->opportunity();

        return $opportunity !== null && ($this->user()?->can('update', $opportunity) ?? false);
    }
}
