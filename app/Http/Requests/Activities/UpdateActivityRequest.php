<?php

namespace App\Http\Requests\Activities;

class UpdateActivityRequest extends ActivityRequest
{
    public function authorize(): bool
    {
        $activity = $this->activity();

        return $activity !== null && ($this->user()?->can('update', $activity) ?? false);
    }
}
