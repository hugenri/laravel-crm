<?php

namespace App\Http\Controllers\Prospects;

use App\Actions\Prospects\ConvertProspect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Prospects\ConvertProspectRequest;
use App\Models\Prospect;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Conversión de un prospecto en cliente con su contacto.
 *
 * Pantalla propia porque la operación crea registros en dos módulos y no se
 * puede deshacer: hay que ver antes de confirmar qué cliente y qué contacto van
 * a quedar registrados. Los datos llegan precargados con lo que ya tiene el
 * prospecto, pero nada se guarda sin confirmarlo aquí.
 */
class ProspectConversionController extends Controller
{
    public function create(Prospect $prospect): Response
    {
        $prospect->load('user');

        return Inertia::render('Prospects/Convert', [
            'prospect' => $prospect,
            'clientTypes' => config('clients.types'),

            // Empresa si registró una, persona si solo hay un nombre: es el
            // valor que más probablemente se vaya a confirmar.
            'initialClientType' => $prospect->company === null ? 'persona' : 'empresa',
            'prefill' => $this->prefill($prospect),
        ]);
    }

    public function store(
        ConvertProspectRequest $request,
        Prospect $prospect,
        ConvertProspect $convertProspect
    ): RedirectResponse {
        try {
            $convertProspect->handle($prospect, $request->validated());
        } catch (DomainException $exception) {
            return redirect()
                ->route('prospects.show', $prospect)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('prospects.show', $prospect)
            ->with('success', __('prospects.converted'));
    }

    /**
     * Lo que el formulario propone como punto de partida.
     *
     * El cliente se propone con la empresa si existe, porque es el nombre con
     * el que va a entrar a la cartera, y con el del prospecto si no la hay.
     *
     * Del nombre del prospecto solo se toma la primera palabra como nombre del
     * contacto: los contactos guardan nombre y apellido por separado y partirlo
     * sin confirmar sería inventar un apellido. El campo queda editable.
     *
     * @return array<string, string>
     */
    private function prefill(Prospect $prospect): array
    {
        $name = trim((string) $prospect->name);

        return [
            'client_name' => $prospect->company ?? $name,
            'rfc' => '',
            'address' => '',
            'client_notes' => '',
            'first_name' => (string) Str::before($name, ' '),
            'last_name' => (string) Str::after($name, ' '),
            'position' => '',
            'phone' => (string) ($prospect->phone ?? ''),
            'email' => (string) ($prospect->email ?? ''),
        ];
    }
}
