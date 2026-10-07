<?php

namespace App\Http\Controllers\Prospects;

use App\Actions\Prospects\ChangeProspectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Prospects\ChangeProspectStatusRequest;
use App\Models\Prospect;
use DomainException;
use Illuminate\Http\RedirectResponse;

/**
 * Movimiento del prospecto dentro del embudo.
 *
 * Ruta propia porque necesita un permiso aparte (prospects.change_status) y el
 * formulario ordinario prohíbe el campo: aquí es donde se decide a qué etapa
 * pasa el registro.
 *
 * "convertido" no se alcanza por aquí: pertenece a la conversión, que además
 * crea el cliente y el contacto.
 */
class ProspectStatusController extends Controller
{
    public function __invoke(
        ChangeProspectStatusRequest $request,
        Prospect $prospect,
        ChangeProspectStatus $changeStatus
    ): RedirectResponse {
        try {
            $changeStatus->handle($prospect, $request->validated('status'));
        } catch (DomainException $exception) {
            return redirect()
                ->route('prospects.show', $prospect)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('prospects.show', $prospect)
            ->with('success', __('prospects.status_updated'));
    }
}
