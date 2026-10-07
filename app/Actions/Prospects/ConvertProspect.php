<?php

namespace App\Actions\Prospects;

use App\Actions\Clients\CreateClient;
use App\Actions\Contacts\CreateContact;
use App\Models\Prospect;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Conversion de un prospecto en cliente con su contacto. Reutiliza CreateClient
 * y CreateContact, asi la conversion no puede contradecir al modulo de clientes,
 * y las tres escrituras van en transaccion porque son indivisibles.
 *
 * No se copia nada en silencio: el usuario confirma los datos precargados. El
 * prospecto no se borra, queda en "convertido" como traza del origen y asi no
 * se convierte dos veces el mismo.
 */
class ConvertProspect
{
    public function __construct(
        private readonly CreateClient $createClient,
        private readonly CreateContact $createContact,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Prospect $prospect, array $data): Prospect
    {
        // Se comprueba fuera y dentro de la transaccion: el estado pudo cambiar
        // entre medias.
        if ($prospect->isConverted()) {
            throw new DomainException(__('prospects.already_converted'));
        }

        return DB::transaction(function () use ($prospect, $data): Prospect {
            // Se vuelve a leer con bloqueo de escritura: dos conversiones
            // simultaneas se serializan y la segunda ve el estado ya convertido
            // en lugar de crear un segundo cliente para el mismo origen.
            $locked = Prospect::query()
                ->lockForUpdate()
                ->findOrFail($prospect->getKey());

            if ($locked->isConverted()) {
                throw new DomainException(__('prospects.already_converted'));
            }

            $client = $this->createClient->handle([
                // El cliente nace activo: la conversion es el alta en la cartera,
                // no un alta suspendida.
                'type' => $data['client_type'],
                'name' => $data['client_name'],
                'rfc' => $data['rfc'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'status' => config('clients.active_status'),
                'notes' => $data['client_notes'] ?? null,
            ]);

            // El contacto nace principal: en una cartera nueva no puede
            // haber otro, y CreateContact lo pasa por SetPrimaryContact
            // como en el resto.
            $contact = $this->createContact->handle($client, [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'position' => $data['position'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'is_primary' => true,
                'notes' => null,
            ]);

            $locked->forceFill([
                'status' => config('prospects.converted_status'),
                'client_id' => $client->getKey(),
                'contact_id' => $contact->getKey(),

                // El seguimiento ya no aplica: el proximo paso se agenda como
                // actividad del cliente.
                'follow_up_date' => null,
            ])->save();

            return $locked->refresh();
        });
    }
}
