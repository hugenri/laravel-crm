<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;

/**
 * Autorización del módulo de cotizaciones.
 *
 * Cada habilidad es solo una comprobación de permiso, como en
 * OpportunityPolicy: quotes.view implica ver todo el listado.
 *
 * update se concede solo mientras la cotización sea editable: en cuanto se
 * envía, el cliente ya la tiene y cambiarle los importes a sus espaldas haría
 * que el guardado no fuera el emitido. Vive aquí y no solo en la interfaz
 * para que una petición a mano reciba la misma respuesta. Ver
 * "editable_statuses" en config/quotes.php.
 *
 * changeStatus es permiso aparte de quotes.update: registrar que el cliente
 * aceptó es una decisión comercial, no un campo del formulario. El PDF tiene
 * permiso propio y no se limita a borradores: una propuesta enviada tiene
 * que poder descargarse mientras espera respuesta.
 */
class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('quotes.view');
    }

    public function view(User $user, Quote $quote): bool
    {
        return $user->can('quotes.view');
    }

    public function create(User $user): bool
    {
        return $user->can('quotes.create');
    }

    /**
     * La edición se limita a las cotizaciones que aún no se enviaron. Ver
     * "editable_statuses" en config/quotes.php.
     */
    public function update(User $user, Quote $quote): bool
    {
        return $user->can('quotes.update') && $quote->isEditable();
    }

    public function delete(User $user, Quote $quote): bool
    {
        return $user->can('quotes.delete');
    }

    public function changeStatus(User $user, Quote $quote): bool
    {
        return $user->can('quotes.change_status');
    }

    /**
     * Permiso propio y no implicado por quotes.view: el PDF lleva folio,
     * importes y nombre del cliente, así que llevarse una copia fuera del
     * sistema no es lo mismo que leer el registro en pantalla.
     *
     * A diferencia de update no se limita a borradores: una propuesta enviada
     * tiene que poder descargarse justo mientras espera respuesta.
     */
    public function pdf(User $user, Quote $quote): bool
    {
        return $user->can('quotes.pdf');
    }
}
