<?php

namespace Database\Factories;

use App\Actions\Quotes\CalculateQuoteTotals;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Folio provisional: la columna es unica y no admite null, y el folio
            // definitivo depende del identificador que la base de datos aun no ha
            // asignado. Se escribe uno aleatorio y configure() lo corrige en
            // cuanto se conoce el id, igual que hace App\Actions\Quotes\SaveQuote.
            'folio' => Str::upper(Str::random(16)),
            'client_id' => Client::factory(),
            'contact_id' => null,
            'opportunity_id' => null,
            'user_id' => User::factory()->state(['is_active' => true]),
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays((int) config('quotes.validity_days'))->toDateString(),
            'status' => config('quotes.initial_status'),
            'tax_rate' => number_format((float) config('quotes.tax_rate'), 2, '.', ''),

            // Los tres importes tambien son provisionales: las columnas no admiten
            // null y los de verdad salen de las partidas, que todavia no existen.
            // refreshTotals() los reemplaza al final de la creacion.
            'subtotal' => '0.00',
            'tax_amount' => '0.00',
            'total' => '0.00',

            'notes' => null,
        ];
    }

    /**
     * Folio, partidas e importes se resuelven despues de crear la fila, no en la
     * definicion, por dos razones:
     *
     *  - el folio se deriva del identificador, igual que hace
     *    App\Actions\Quotes\SaveQuote, para que una cotizacion creada en un test y
     *    una creada desde la interfaz numeren de la misma manera y no haya dos
     *    reglas de foliado en el proyecto;
     *
     *  - los importes salen de las partidas con el mismo calculador que usa el
     *    alta real, de modo que la suma del detalle siempre cuadre con el
     *    encabezado y un test no pueda pasar con un total inventado.
     *
     * Los dos callbacks los registra withItems(), que aqui se llama sin
     * argumento para dejar que sortee el numero de partidas.
     */
    public function configure(): static
    {
        return $this->withItems();
    }

    /**
     * Los totales se vuelven a calcular con el calculador real y no se copian de
     * las partidas a mano, para que un cambio en las reglas de redondeo no deje
     * una fabrica que produce cotizaciones imposibles.
     */
    private function refreshTotals(Quote $quote): void
    {
        $totals = app(CalculateQuoteTotals::class)->handle(
            $quote->items()->get(['description', 'quantity', 'unit_price'])->toArray(),
            $quote->tax_rate,
        );

        $quote->forceFill([
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax_amount'],
            'total' => $totals['total'],
        ])->save();
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (array $attributes): array => [
            'client_id' => $client->getKey(),
        ]);
    }

    /**
     * Asociar un contacto, opcionalmente indicando cual.
     *
     * El cliente todavia puede ser una fabrica sin resolver cuando este estado se
     * evalua, porque las relaciones se crean al final. Por eso el cliente se
     * resuelve aqui y se vuelve a fijar en "client_id": si no, la cotizacion y el
     * contacto se crearian con clientes distintos y el contacto dejaria de
     * pertenecer al cliente de la cotizacion, que es justo la integridad que el
     * modulo garantiza. Mismo criterio que en OpportunityFactory.
     */
    public function withContact(?Contact $contact = null): static
    {
        return $this->state(function (array $attributes) use ($contact): array {
            $client = $this->resolveClient($attributes['client_id'] ?? null);

            return [
                'client_id' => $client->getKey(),
                'contact_id' => ($contact ?? $client->contacts()->create([
                    'first_name' => fake()->firstName(),
                    'last_name' => fake()->lastName(),
                    'email' => fake()->unique()->safeEmail(),
                    'phone' => fake()->numerify('##########'),
                ]))->getKey(),
            ];
        });
    }

    /**
     * Asociar una oportunidad, que arrastra su cliente: una cotizacion no puede
     * colgar de una oportunidad de otra empresa.
     */
    public function forOpportunity(Opportunity $opportunity): static
    {
        return $this->state(fn (array $attributes): array => [
            'client_id' => $opportunity->client_id,
            'opportunity_id' => $opportunity->getKey(),
        ]);
    }

    public function responsible(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->getKey(),
        ]);
    }

    public function status(string $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
        ]);
    }

    public function sent(): static
    {
        return $this->status('enviada');
    }

    public function accepted(): static
    {
        return $this->status('aceptada');
    }

    public function rejected(): static
    {
        return $this->status('rechazada');
    }

    /**
     * Vigencia ya pasada. El estado se deja como este, el inicial, porque lo
     * interesante del estado es justamente que "vencida" se deriva de la fecha y
     * no de la columna.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'issue_date' => now()->subDays(30)->toDateString(),
            'valid_until' => now()->subDay()->toDateString(),
        ]);
    }

    /**
     * Fija cuantas partidas tiene la cotizacion; sin argumento, las que toque.
     *
     * Registra de nuevo los dos callbacks en lugar de agregar uno, porque
     * withoutAfterCreating() es la unica forma de sustituir el que ya habia
     * dejado configure() y sumar en vez de sustituir daria partidas de las dos
     * fuentes. El numero viaja capturado en la closure, y no en una propiedad,
     * porque count() y state() construyen una fabrica nueva cada vez (newInstance
     * no clona) y una propiedad no sobrevive a eso: con una propiedad,
     * withItems(3) se escribiria en una instancia que el callback ya nunca mira
     * y las partidas serian las que se sortearon al crear la fabrica. La
     * consequence de esto es que nada mas puede registrar callbacks de
     * creacion en QuoteFactory: se perderian al volver a pasar por aqui.
     */
    public function withItems(?int $count = null): static
    {
        $count ??= fake()->numberBetween(1, 3);

        return $this->withoutAfterCreating()
            ->afterCreating(function (Quote $quote) use ($count): void {
                // Una por una, y no con count(), para que la posicion se asigne en
                // el orden en que se crean. count() repartiria posiciones al azar
                // y el detalle se mostraria desordenado, con numeros de linea que
                // no empiezan en uno.
                for ($position = 1; $position <= $count; $position++) {
                    QuoteItem::factory()
                        ->for($quote)
                        ->create(['position' => $position]);
                }

                // Al final, y no antes, porque el total es la suma de unas partidas
                // que todavia no existen.
                $this->refreshTotals($quote);
            })
            ->afterCreating(function (Quote $quote): void {
                $quote->forceFill([
                    'folio' => config('quotes.folio_prefix').str_pad(
                        (string) $quote->getKey(),
                        (int) config('quotes.folio_padding'),
                        '0',
                        STR_PAD_LEFT,
                    ),
                ])->save();
            });
    }

    /**
     * Cotizacion sin partidas, para probar que el backend las exige y que el
     * calculador no produce un total sobre un detalle vacio.
     */
    public function withoutItems(): static
    {
        return $this->withItems(0);
    }

    /**
     * El cliente que la cotizacion va a usar, ya creado, para poder colgarle un
     * contacto que pertenezca a ese mismo cliente.
     */
    private function resolveClient(mixed $value): Client
    {
        if ($value instanceof Client) {
            return $value;
        }

        if ($value instanceof ClientFactory || $value instanceof Factory) {
            return $value->create();
        }

        if ($value === null || $value === '') {
            return Client::factory()->create();
        }

        return Client::findOrFail($value);
    }
}
