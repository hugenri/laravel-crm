<?php

namespace App\Http\Requests\Opportunities;

class StoreOpportunityRequest extends OpportunityRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('opportunities.create') ?? false;
    }
}
