<?php

namespace App\Actions\Opportunities;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Opportunity;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Alta de una oportunidad.
 *
 * El estado no se recibe: se deriva de la etapa. Ver
 * App\Actions\Opportunities\DeriveOpportunityStatus.
 */
class CreateOpportunity
{
    public function __construct(private readonly DeriveOpportunityStatus $deriveStatus) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws DomainException
     */
    public function handle(array $data): Opportunity
    {
        return DB::transaction(function () use ($data): Opportunity {
            $client = Client::findOrFail($data['client_id']);
            $contact = $this->contact($data['contact_id'] ?? null, $client);

            $stage = $data['stage'] ?? config('opportunities.initial_stage');

            $opportunity = new Opportunity;

            // forceFill y no create(): "status" no es Fillable a proposito, y
            // aqui es el unico sitio que lo escribe.
            $opportunity->forceFill([
                'client_id' => $client->getKey(),
                'contact_id' => $contact?->getKey(),
                'user_id' => $data['user_id'],
                'name' => trim($data['name']),
                'amount' => $this->amount($data['amount'] ?? null),
                'stage' => $stage,
                'status' => $this->deriveStatus->fromStage($stage),
                'expected_close_date' => $data['expected_close_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ])->save();

            return $opportunity->refresh();
        });
    }

    /**
     * El contacto tiene que pertenecer al cliente. El form request ya lo
     * comprueba, pero aqui se repite porque esta clase es la frontera del
     * dominio.
     *
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
