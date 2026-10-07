<?php

namespace App\Actions\Quotes;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Alta y edicion de una cotizacion, en una sola clase como SaveProspect.
 *
 * Aqui se calcula lo que no llega del formulario: subtotal, impuesto y total,
 * el folio y el estado inicial.
 *
 * Las partidas se reemplazan por completo en cada edicion, dentro de la misma
 * transaccion que actualiza el encabezado: si algo falla a la mitad, se deshace
 * todo y queda el detalle anterior, nunca la mitad del nuevo.
 */
class SaveQuote
{
    public function __construct(private readonly CalculateQuoteTotals $calculate) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws DomainException
     */
    public function create(array $data): Quote
    {
        return DB::transaction(function () use ($data): Quote {
            $client = Client::findOrFail($data['client_id']);
            $contact = $this->contact($data['contact_id'] ?? null, $client);
            $opportunity = $this->opportunity($data['opportunity_id'] ?? null, $client);
            $user = $this->user($data['user_id'] ?? null);

            $totals = $this->calculate->handle($data['items'] ?? [], $data['tax_rate'] ?? null);

            $quote = new Quote;

            // forceFill y no create(): folio, estado y los tres totales no son
            // Fillable a proposito, aqui es el unico sitio que los escribe.
            $quote->forceFill([
                // Folio provisional: la columna es unica y no admite null, y
                // el definitivo depende del id que MySQL aun no ha asignado. Se
                // corrige en cuanto se conoce, en la misma transaccion.
                'folio' => Str::upper(Str::random(16)),
                'client_id' => $client->getKey(),
                'contact_id' => $contact?->getKey(),
                'opportunity_id' => $opportunity?->getKey(),
                'user_id' => $user->getKey(),
                'issue_date' => $data['issue_date'],
                'valid_until' => $data['valid_until'],
                'status' => config('quotes.initial_status'),
                'subtotal' => $totals['subtotal'],
                'tax_rate' => $totals['tax_rate'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'notes' => $data['notes'] ?? null,
            ])->save();

            $quote->forceFill(['folio' => $this->folio($quote->getKey())])->save();

            $this->storeItems($quote, $totals['items']);

            return $quote->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws DomainException
     */
    public function update(Quote $quote, array $data): Quote
    {
        return DB::transaction(function () use ($quote, $data): Quote {
            $client = Client::findOrFail($data['client_id']);
            $contact = $this->contact($data['contact_id'] ?? null, $client);
            $opportunity = $this->opportunity($data['opportunity_id'] ?? null, $client);
            $user = $this->user($data['user_id'] ?? null);

            $totals = $this->calculate->handle($data['items'] ?? [], $data['tax_rate'] ?? null);

            // El estado no se toca: se cambia con la accion que tiene su
            // propio permiso, no de paso al editar.
            $quote->forceFill([
                'client_id' => $client->getKey(),
                'contact_id' => $contact?->getKey(),
                'opportunity_id' => $opportunity?->getKey(),
                'user_id' => $user->getKey(),
                'issue_date' => $data['issue_date'],
                'valid_until' => $data['valid_until'],
                'tax_rate' => $totals['tax_rate'],
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'notes' => $data['notes'] ?? null,
            ])->save();

            $quote->items()->delete();
            $this->storeItems($quote, $totals['items']);

            return $quote->refresh();
        });
    }

    /**
     * El folio se deriva del id, unico y nunca reutilizado por ser
     * autoincremental. Un contador aparte tendria que bloquear su fila dentro
     * de la transaccion y podria desincronizarse.
     */
    private function folio(int $id): string
    {
        return config('quotes.folio_prefix').str_pad(
            (string) $id,
            (int) config('quotes.folio_padding'),
            '0',
            STR_PAD_LEFT,
        );
    }

    /**
     * @param  list<array{position: int, description: string, quantity: string, unit_price: string, amount: string}>  $items
     */
    private function storeItems(Quote $quote, array $items): void
    {
        foreach ($items as $item) {
            // No se usa create() porque "amount" no es Fillable a proposito:
            // lo escribe el calculador, nunca el formulario. create() descartaria
            // el importe en silencio y la columna, que no admite null, haria
            // fallar el guardado.
            $quoteItem = new QuoteItem([
                'position' => $item['position'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
            ]);

            $quoteItem->forceFill(['amount' => $item['amount']]);

            $quote->items()->save($quoteItem);
        }
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
            throw new DomainException(__('quotes.contact_of_another_client'));
        }

        return $contact;
    }

    /**
     * La oportunidad tiene que pertenecer al cliente, por la misma razon que
     * el contacto: no puede ser la de otra empresa.
     *
     * @throws DomainException
     */
    private function opportunity(mixed $opportunityId, Client $client): ?Opportunity
    {
        if ($opportunityId === null || $opportunityId === '') {
            return null;
        }

        $opportunity = Opportunity::find($opportunityId);

        if ($opportunity === null || $opportunity->client_id !== $client->getKey()) {
            throw new DomainException(__('quotes.opportunity_of_another_client'));
        }

        return $opportunity;
    }

    /**
     * El responsable tiene que existir y estar activo: una propuesta no puede
     * quedar a cargo de una cuenta dada de baja. El form request ya lo
     * comprueba, aqui se repite por ser la frontera del dominio.
     *
     * @throws DomainException
     */
    private function user(mixed $userId): User
    {
        $user = $userId === null || $userId === '' ? null : User::find($userId);

        if ($user === null) {
            throw new DomainException(__('quotes.user_required'));
        }

        if (! $user->isActive()) {
            throw new DomainException(__('quotes.user_inactive'));
        }

        return $user;
    }
}
