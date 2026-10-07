<?php

namespace App\Actions\Quotes;

use App\Models\Quote;
use DomainException;

/**
 * Cambio de estado de una cotizacion. Es la unica via para transitar entre
 * estados, y por eso tiene permiso propio (quotes.change_status). Solo cambia
 * lo que el CRM sabe del documento: nunca importes, partidas ni fechas.
 *
 * Las transiciones permitidas estan en config/quotes.php. "Aceptada" significa
 * que el cliente contesto, asi que no se registra su respuesta a una propuesta
 * que nunca se le envio.
 */
class ChangeQuoteStatus
{
    public function handle(Quote $quote, string $status): Quote
    {
        $from = $quote->status;

        if ($from === $status) {
            return $quote;
        }

        $allowed = config('quotes.transitions')[$from] ?? [];

        if (! in_array($status, $allowed, true)) {
            throw new DomainException(__('quotes.invalid_transition', [
                'from' => config('quotes.statuses')[$from] ?? $from,
                'to' => config('quotes.statuses')[$status] ?? $status,
            ]));
        }

        // forceFill y no fill() porque "status" no es Fillable a proposito.
        $quote->forceFill(['status' => $status])->save();

        return $quote->refresh();
    }

    /**
     * Estados a los que se puede llegar desde el actual, calculados con la
     * misma tabla que valida la accion, para que la interfaz no ofrezca un
     * destino que el backend vaya a rechazar.
     *
     * @return list<string>
     */
    public function availableFor(Quote $quote): array
    {
        return config('quotes.transitions')[$quote->status] ?? [];
    }
}
