<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'empresa',
            'name' => fake()->unique()->company(),
            'rfc' => null,
            'phone' => fake()->numerify('## #### ####'),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->address(),
            'status' => config('clients.active_status'),
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'inactivo',
        ]);
    }

    public function company(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'empresa',
        ]);
    }

    public function person(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'persona',
        ]);
    }

    /**
     * RFC de persona moral, que es el de 12 caracteres: 3 iniciales, 6 de
     * fecha y 3 de homoclave. El contador evita repetir el valor, porque la
     * columna es unica.
     */
    public function withRfc(?string $rfc = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'rfc' => $rfc ?? 'EMP'.fake()->unique()->numerify('######ABC'),
        ]);
    }
}
