<?php

namespace App\Http\Requests\Activities;

class StoreActivityRequest extends ActivityRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('activities.create') ?? false;
    }
}
