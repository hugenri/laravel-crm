<?php

namespace App\Models;

use Database\Factories\QuoteItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una partida de una cotización.
 *
 * "description" es texto libre a propósito: no hay catálogo de productos, es
 * un módulo aparte. "amount" lo calcula el servidor, por eso no es Fillable.
 * Ver SaveQuote.
 */
#[Fillable(['quote_id', 'position', 'description', 'quantity', 'unit_price'])]
class QuoteItem extends Model
{
    /** @use HasFactory<QuoteItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Tres decimales para horas, kilos o metros; los importes usan dos.
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Cotizacion a la que pertenece la partida.
     *
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
