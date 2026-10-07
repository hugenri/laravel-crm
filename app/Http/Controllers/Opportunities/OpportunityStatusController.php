<?php

namespace App\Http\Controllers\Opportunities;

use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Opportunities\ChangeOpportunityStatusRequest;
use App\Models\Opportunity;
use Illuminate\Http\RedirectResponse;

/**
 * Cierre y reapertura de una oportunidad.
 *
 * Ruta propia porque necesita un permiso aparte (opportunities.change_status) y
 * el formulario ordinario solo ofrece etapas abiertas: aquí es donde se decide
 * con qué etapa cierra y si se reabre.
 */
class OpportunityStatusController extends Controller
{
    public function __invoke(
        ChangeOpportunityStatusRequest $request,
        Opportunity $opportunity,
        ChangeOpportunityStatus $changeStatus
    ): RedirectResponse {
        $changeStatus->handle($opportunity, $request->validated('status'));

        return redirect()
            ->route('opportunities.show', $opportunity)
            ->with('success', __('opportunities.status_updated'));
    }
}
