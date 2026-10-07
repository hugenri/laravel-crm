<?php

namespace App\Http\Requests\Quotes;

class StoreQuoteRequest extends QuoteRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('quotes.create') ?? false;
    }
}
