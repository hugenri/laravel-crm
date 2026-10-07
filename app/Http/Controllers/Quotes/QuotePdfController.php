<?php

namespace App\Http\Controllers\Quotes;

use App\Actions\Quotes\GenerateQuotePdf;
use App\Http\Controllers\Controller;
use App\Models\Quote;
use Illuminate\Http\Response;

/**
 * Descarga del PDF de una cotización.
 *
 * Su propio permiso (quotes.pdf), distinto de quotes.view: el documento lleva
 * folio, importes y cliente, así que quien se lleva una copia fuera del sistema
 * no es necesariamente quien consulta el registro en pantalla.
 *
 * Tampoco exige quotes.update, al contrario que la edición: una propuesta
 * enviada tiene que poder descargarse mientras espera respuesta.
 */
class QuotePdfController extends Controller
{
    public function __invoke(Quote $quote, GenerateQuotePdf $generatePdf): Response
    {
        return $generatePdf->handle($quote);
    }
}
