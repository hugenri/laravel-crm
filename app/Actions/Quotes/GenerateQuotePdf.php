<?php

namespace App\Actions\Quotes;

use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Genera el PDF de la propuesta. Vive en una action y no en el controlador
 * para que el armado del documento tenga un solo lugar y se pueda generar sin
 * pasar por la web.
 *
 * Usa una vista Blade propia y no la de Inertia: dompdf no ejecuta JavaScript,
 * y los importes impresos son los que guardo el servidor, de modo que el PDF y
 * la pantalla no pueden discrepar.
 *
 * El permiso quotes.pdf lo comprueba QuotePolicy::pdf, no esta clase.
 */
class GenerateQuotePdf
{
    public function handle(Quote $quote): Response
    {
        $quote->loadMissing(['client', 'contact', 'opportunity', 'user', 'items']);

        return Pdf::loadView('quotes.pdf', [
            'quote' => $quote,
            'items' => $quote->items,
            'statuses' => config('quotes.statuses'),
        ])->download('cotizacion-'.$quote->folio.'.pdf');
    }
}
