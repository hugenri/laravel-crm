<?php

namespace App\Actions\Quotes;

use App\Models\Quote;

/**
 * Borrado de una cotizacion y sus partidas (cascadeOnDelete). No toca cliente,
 * contacto, oportunidad ni responsable.
 *
 * No hay condiciones que impidan borrar: quotes.delete solo lo tiene el
 * administrador, y una propuesta que ya no interesa se retira.
 */
class DeleteQuote
{
    public function handle(Quote $quote): void
    {
        $quote->delete();
    }
}
