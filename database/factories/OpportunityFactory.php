<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opportunity>
 */
class OpportunityFactory extends Factory
{
    protected $model = Opportunity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'contact_id' => null,
            'user_id' => User::factory()->state(['is_active' => true]),
            'name' => fake()->unique()->catchPhrase(),
            'amount' => fake()->randomFloat(2, 5000, 500000),
            'stage' => config('opportunities.initial_stage'),
            'status' => config('opportunities.initial_status'),
            'expected_close_date' => fake()->dateTimeBetween('+1 week', '+3 months')->format('Y-m-d'),
            'notes' => null,
        ];
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
     * El cliente todavia puede ser una fabrica sin resolver cuando este estado
     * se evalua, porque las relaciones se crean al final. Por eso el cliente se
     * resuelve aqui y se vuelve a fijar en "client_id": si no, la oportunidad y
     * el contacto se crearian con clientes distintos y el contacto dejaria de
     * pertenecer al cliente de la oportunidad, que es justo la integridad que el
     * modulo garantiza.
     */
    public function withContact(?Contact $contact = null): static
    {
        return $this->state(function (array $attributes) use ($contact): array {
            $client = $this->resolveClient($attributes['client_id'] ?? null);

            return [
                'client_id' => $client->getKey(),
                'contact_id' => ($contact ?? Contact::factory()->for($client, 'client')->create())->getKey(),
            ];
        });
    }

    /**
     * El cliente que la oportunidad va a usar, ya creado, para poder colgarle un
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

    public function responsible(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->getKey(),
        ]);
    }

    public function stage(string $stage): static
    {
        return $this->state(fn (array $attributes): array => [
            'stage' => $stage,
        ]);
    }

    /**
     * Cierre: la etapa y el estado toman juntos el valor de la etapa de cierre,
     * igual que haria la accion de cambio de estado. No se escribe un estado
     * sin su etapa, porque eso violaria el invariante del modulo.
     */
    public function won(): static
    {
        return $this->closingStage('ganada');
    }

    public function lost(): static
    {
        return $this->closingStage('perdida');
    }

    public function closed(): static
    {
        return $this->closingStage('ganada');
    }

    private function closingStage(string $stage): static
    {
        return $this->state(fn (array $attributes): array => [
            'stage' => $stage,
            'status' => $stage,
        ]);
    }
}
