<?php

namespace App\Actions\Activities;

use App\Models\Activity;

/**
 * Borrado de una actividad.
 *
 * Sin papelera ni registros dependientes: se elimina la fila directamente.
 */
class DeleteActivity
{
    public function handle(Activity $activity): void
    {
        $activity->delete();
    }
}
