<?php

namespace App\Actions\Opportunities;

use App\Models\Opportunity;

/**
 * Cerrar y reabrir una oportunidad. Es la unica via a una etapa de cierre, y
 * por eso tiene permiso propio (opportunities.change_status).
 *
 * Cerrar no borra nada: reabrir la devuelve a "Abierta" conservando la etapa,
 * para reintentar el trato sin crear un registro nuevo.
 */
class ChangeOpportunityStatus
{
    public function __construct(private readonly DeriveOpportunityStatus $deriveStatus) {}

    public function handle(Opportunity $opportunity, string $status): Opportunity
    {
        if ($status === config('opportunities.open_status')) {
            // Reabrir: solo cambia el estado, la etapa se conserva aunque sea
            // de cierre, porque describe por donde iba la operacion.
            $opportunity->forceFill([
                'status' => $status,
            ])->save();

            return $opportunity->refresh();
        }

        // Cerrar: la etapa se deriva del estado, quien cierra declara el
        // resultado.
        $opportunity->forceFill([
            'stage' => $this->deriveStatus->stageForStatus($status),
            'status' => $status,
        ])->save();

        return $opportunity->refresh();
    }
}
