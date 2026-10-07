<?php

namespace App\Http\Requests\Quotes;

class UpdateQuoteRequest extends QuoteRequest
{
    public function authorize(): bool
    {
        $quote = $this->quote();

        return $quote !== null && ($this->user()?->can('update', $quote) ?? false);
    }
}
