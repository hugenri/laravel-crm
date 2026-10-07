<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prospect>
 */
class ProspectFactory extends Factory
{
    protected $model = Prospect::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['is_active' => true]),
            'name' => fake()->name(),
            'company' => fake()->company(),
            'phone' => fake()->numerify('55########'),
            'email' => fake()->unique()->safeEmail(),
            'source' => fake()->randomElement(array_keys(config('prospects.sources'))),
            'status' => config('prospects.initial_status'),
            'follow_up_date' => fake()->dateTimeBetween('+1 day', '+3 months')->format('Y-m-d'),
            'notes' => null,
            'client_id' => null,
            'contact_id' => null,
        ];
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

    public function source(string $source): static
    {
        return $this->state(fn (array $attributes): array => [
            'source' => $source,
        ]);
    }

    public function qualified(): static
    {
        return $this->status('calificado');
    }

    public function notInterested(): static
    {
        return $this->status('no_interesado');
    }

    /**
     * Prospecto ya convertido, con el cliente y el contacto que genero la
     * conversion. Se construye en lugar de llamar a la accion porque el factory
     * describe el estado final de la fila, no como se llego a el.
     */
    public function converted(?Client $client = null, ?Contact $contact = null): static
    {
        return $this->state(function (array $attributes) use ($client, $contact): array {
            $client ??= Client::factory()->create();
            $contact ??= Contact::factory()->create(['client_id' => $client->getKey()]);

            return [
                'status' => config('prospects.converted_status'),
                'client_id' => $client->getKey(),
                'contact_id' => $contact->getKey(),
            ];
        });
    }
}
