<?php

namespace App\Actions\Opportunities;

use DomainException;

/**
 * Unico sitio que decide el estado de una oportunidad a partir de su etapa.
 * El estado nunca se escribe como un valor aparte: siempre se deriva aqui, de
 * modo que etapa y estado no puedan quedar incoherentes.
 *
 * Cerrar como ganada o perdida pasa por ChangeOpportunityStatus, el unico
 * sitio permitido para entrar en una etapa de cierre.
 */
class DeriveOpportunityStatus
{
    /**
     * @throws DomainException when the stage is not part of the catalog
     */
    public function fromStage(string $stage): string
    {
        $stages = config('opportunities.stages');

        if (! array_key_exists($stage, $stages)) {
            throw new DomainException(__('opportunities.invalid_stage'));
        }

        $closing = config('opportunities.closing_stages');

        // Una etapa de cierre trae su estado consigo.
        if (array_key_exists($stage, $closing)) {
            return $closing[$stage];
        }

        // Cualquier otra etapa deja la operacion en curso: salir de una etapa
        // de cierre reabre implicitamente en lugar de dejar un estado cerrado
        // que la etapa contradiga.
        return config('opportunities.open_status');
    }

    /**
     * Etapa de cierre que produce un estado, o null si el estado no es de
     * cierre. Se usa al cerrar: el llamador nombra el resultado y la etapa lo
     * sigue.
     *
     * @throws DomainException when the status cannot be reached from a stage
     */
    public function stageForStatus(string $status): string
    {
        $stage = array_search($status, config('opportunities.closing_stages'), true);

        if ($stage === false) {
            throw new DomainException(__('opportunities.invalid_status'));
        }

        return $stage;
    }
}
