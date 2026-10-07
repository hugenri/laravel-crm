<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'opportunity_id' => null,
            'user_id' => User::factory()->state(['is_active' => true]),
            'type' => 'llamada',
            'activity_date' => fake()->dateTimeBetween('-1 month', '+1 month'),
            'status' => config('activities.initial_status'),
            'description' => fake()->sentence(),
        ];
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (array $attributes): array => [
            'client_id' => $client->getKey(),
        ]);
    }

    /**
     * Vincular la actividad a una oportunidad, opcionalmente indicando cual.
     *
     * El cliente de la actividad y el de la oportunidad deben coincidir, que
     * es justo la integridad que el modulo garantiza en el backend. Por eso el
     * cliente se resuelve aqui y se fija en ambas columnas, igual que hace
     * OpportunityFactory::withContact con el contacto.
     */
    public function forOpportunity(?Opportunity $opportunity = null): static
    {
        return $this->state(function (array $attributes) use ($opportunity): array {
            $opportunity ??= Opportunity::factory()->create();

            return [
                'client_id' => $opportunity->client_id,
                'opportunity_id' => $opportunity->getKey(),
            ];
        });
    }

    public function type(string $type): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => $type,
        ]);
    }

    public function status(string $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
        ]);
    }

    public function on(mixed $date): static
    {
        return $this->state(fn (array $attributes): array => [
            'activity_date' => $date,
        ]);
    }

    public function responsible(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->getKey(),
        ]);
    }
}
