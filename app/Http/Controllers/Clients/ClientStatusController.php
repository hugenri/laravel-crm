<?php

namespace App\Http\Controllers\Clients;

use App\Actions\Clients\ChangeClientStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ChangeClientStatusRequest;
use App\Models\Client;
use DomainException;
use Illuminate\Http\RedirectResponse;

/**
 * Ruta propia porque clients.change_status es un permiso distinto de
 * clients.update: quien puede corregir datos no necesariamente retira clientes
 * de la operación.
 */
class ClientStatusController extends Controller
{
    public function __invoke(
        ChangeClientStatusRequest $request,
        Client $client,
        ChangeClientStatus $changeClientStatus,
    ): RedirectResponse {
        try {
            $changeClientStatus->handle($client, $request->validated('status'));
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('clients.status_updated'));
    }
}
