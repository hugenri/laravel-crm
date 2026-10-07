<?php

namespace Database\Factories;

use App\Models\QuoteItem;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteItem>
 */
class QuoteItemFactory extends Factory
{
    protected $model = QuoteItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(3, 1, 10);
        $unitPrice = fake()->randomFloat(2, 150, 5000);

        return [
            // La posicion real la asigna QuoteFactory, que crea las partidas una
            // por una y por eso sabe en que orden van. Aqui solo hace falta un
            // valor valido para el caso de crear una partida suelta.
            'position' => 1,
            'description' => fake()->randomElement([
                'Desarrollo e integración de la plataforma',
                'Licencia de software anual',
                'Soporte y mantenimiento mensual',
                'Capacitación de usuarios',
                'Consultoría de implementación',
                'Hosting y respaldo mensual',
            ]),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,

            // El importe de la linea se calcula con la misma aritmetica de
            // enteros que usa App\Actions\Quotes\CalculateQuoteTotals. Calcularlo
            // con un float aqui haria que el detalle de una cotizacion de prueba
            // no sumara exactamente el subtotal del encabezado.
            'amount' => Money::fromCents(Money::lineAmount($quantity, $unitPrice)),
        ];
    }

    /**
     * Una partida con valores concretos, para las pruebas de calculo.
     */
    public function line(string $description, string $quantity, string $unitPrice): static
    {
        return $this->state(fn (array $attributes): array => [
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'amount' => Money::fromCents(Money::lineAmount($quantity, $unitPrice)),
        ]);
    }
}
