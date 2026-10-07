<?php

namespace App\Actions\Opportunities;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Opportunity;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Edicion de una oportunidad. Cada campo se escribe solo si llego en la
 * peticion. El estado se vuelve a derivar al cambiar la etapa, para que mover
 * la oportunidad fuera de una etapa de cierre la reabra de forma coherente.
 */
class UpdateOpportunity
{
    public function __construct(private readonly DeriveOpportunityStatus $deriveStatus) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws DomainException
     */
    public function handle(Opportunity $opportunity, array $data): Opportunity
    {
        return DB::transaction(function () use ($opportunity, $data): Opportunity {
            // El cliente si se puede cambiar; al hacerlo el contacto tiene que
            // ser del cliente nuevo o ninguno.
            $client = $opportunity->client;
            $clientChanged = false;

            if (array_key_exists('client_id', $data)) {
                $client = Client::findOrFail($data['client_id']);
                $clientChanged = $client->getKey() !== $opportunity->client_id;
                $opportunity->client_id = $client->getKey();
            }

            if (array_key_exists('contact_id', $data)) {
                $opportunity->contact_id = $this->contact($data['contact_id'], $client)?->getKey();
            } elseif ($clientChanged && $opportunity->contact_id !== null) {
                // El cliente cambio sin que la peticion tocara el contacto.
                // Revalidar evita heredar un contacto de la empresa anterior.
                $opportunity->contact_id = $this->contact($opportunity->contact_id, $client)?->getKey();
            }

            if (array_key_exists('user_id', $data)) {
                $opportunity->user_id = $data['user_id'];
            }

            if (array_key_exists('name', $data)) {
                $opportunity->name = trim($data['name']);
            }

            if (array_key_exists('amount', $data)) {
                $opportunity->amount = $this->amount($data['amount']);
            }

            if (array_key_exists('expected_close_date', $data)) {
                $opportunity->expected_close_date = $data['expected_close_date'] ?: null;
            }

            if (array_key_exists('notes', $data)) {
                $opportunity->notes = $data['notes'] ?: null;
            }

            if (array_key_exists('stage', $data)) {
                $opportunity->stage = $data['stage'];
                $opportunity->status = $this->deriveStatus->fromStage($data['stage']);
            }

            $opportunity->save();

            return $opportunity->refresh();
        });
    }

    /**
     * @throws DomainException
     */
    private function contact(mixed $contactId, Client $client): ?Contact
    {
        if ($contactId === null || $contactId === '') {
            return null;
        }

        $contact = Contact::find($contactId);

        if ($contact === null || $contact->client_id !== $client->getKey()) {
            throw new DomainException(__('opportunities.contact_of_another_client'));
        }

        return $contact;
    }

    /**
     * El formulario puede mandar "12,500.50" con separador de miles. Se quitan
     * los separadores y se normaliza a dos decimales.
     *
     * @throws DomainException
     */
    private function amount(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $cleaned = str_replace([',', ' ', "\u{00A0}"], '', trim((string) $value));

        if (! is_numeric($cleaned)) {
            throw new DomainException(__('opportunities.invalid_amount'));
        }

        return number_format((float) $cleaned, 2, '.', '');
    }
}
