<?php

namespace App\Http\Controllers\Quotes;

use App\Actions\Quotes\ChangeQuoteStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quotes\ChangeQuoteStatusRequest;
use App\Models\Quote;
use DomainException;
use Illuminate\Http\RedirectResponse;

/**
 * Cambio de estado de una cotización.
 *
 * Ruta propia, como en oportunidades y prospectos: necesita un permiso aparte
 * (quotes.change_status) y el formulario ordinario ni siquiera lo ofrece,
 * porque una propuesta se envía y se acepta como una decisión, no como un campo
 * más que se guarda al guardar.
 */
class QuoteStatusController extends Controller
{
    public function __invoke(
        ChangeQuoteStatusRequest $request,
        Quote $quote,
        ChangeQuoteStatus $changeStatus
    ): RedirectResponse {
        try {
            $changeStatus->handle($quote, $request->validated('status'));
        } catch (DomainException $exception) {
            // El estado existe en el catálogo, pero no se llega a él desde el
            // actual. Depende de la cotización, no de la forma, así que no cabe
            // en un error de campo.
            return redirect()
                ->route('quotes.show', $quote)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('quotes.show', $quote)
            ->with('success', __('quotes.status_updated'));
    }
}
