<?php

namespace App\Actions\Quotes;

use App\Support\Money;
use DomainException;
use InvalidArgumentException;

/**
 * Calculo de los importes de una cotizacion. No toca la base de datos: recibe
 * las partidas del formulario y devuelve las normalizadas mas los totales.
 *
 * El navegador nunca manda subtotal, impuesto ni total: si los mandara, el
 * calculo del servidor ganaria. Toda la aritmetica es de enteros, ver
 * App\Support\Money, para que el PDF no discrepe de la pantalla.
 */
class CalculateQuoteTotals
{
    /**
     * @param  array<int, array{description?: mixed, quantity?: mixed, unit_price?: mixed}>  $items
     * @return array{
     *     items: list<array{position: int, description: string, quantity: string, unit_price: string, amount: string}>,
     *     subtotal: string,
     *     tax_rate: string,
     *     tax_amount: string,
     *     total: string
     * }
     *
     * @throws DomainException
     */
    public function handle(array $items, mixed $taxRate): array
    {
        $rows = [];
        $subtotalCents = 0;

        foreach (array_values($items) as $index => $item) {
            $quantity = $this->thousandths($item['quantity'] ?? null, 'quantity', $index);
            $unitPrice = $this->cents($item['unit_price'] ?? null, 'unit_price', $index);

            // Ya vienen en milimas y centimos enteros, asi que se multiplica
            // con la variante que no los vuelve a convertir.
            $amountCents = Money::lineAmountInCents($quantity, $unitPrice);

            $subtotalCents += $amountCents;

            $rows[] = [
                // La posicion la asigna el servidor por el orden de llegada,
                // no el navegador.
                'position' => $index + 1,
                'description' => trim((string) ($item['description'] ?? '')),
                'quantity' => Money::fromThousandths($quantity),
                'unit_price' => Money::fromCents($unitPrice),
                'amount' => Money::fromCents($amountCents),
            ];
        }

        $rateHundredths = Money::rateToHundredths($this->taxRate($taxRate));
        $taxCents = Money::tax($subtotalCents, $rateHundredths);
        $totalCents = $subtotalCents + $taxCents;

        $this->guardTotal($totalCents);

        return [
            'items' => $rows,
            'subtotal' => Money::fromCents($subtotalCents),
            'tax_rate' => Money::fromCents($rateHundredths),
            'tax_amount' => Money::fromCents($taxCents),
            'total' => Money::fromCents($totalCents),
        ];
    }

    /**
     * @throws DomainException
     */
    private function thousandths(mixed $value, string $field, int $index): int
    {
        try {
            $thousandths = Money::toThousandths($value ?? '');
        } catch (InvalidArgumentException) {
            throw new DomainException(__('quotes.invalid_item_value', [
                'field' => __('quotes.'.$field),
                'position' => $index + 1,
            ]));
        }

        if ($thousandths <= 0) {
            throw new DomainException(__('quotes.non_positive_item_value', [
                'field' => __('quotes.'.$field),
                'position' => $index + 1,
            ]));
        }

        return $thousandths;
    }

    /**
     * @throws DomainException
     */
    private function cents(mixed $value, string $field, int $index): int
    {
        try {
            $cents = Money::toCents($value ?? '');
        } catch (InvalidArgumentException) {
            throw new DomainException(__('quotes.invalid_item_value', [
                'field' => __('quotes.'.$field),
                'position' => $index + 1,
            ]));
        }

        if ($cents < 0) {
            throw new DomainException(__('quotes.negative_item_value', [
                'field' => __('quotes.'.$field),
                'position' => $index + 1,
            ]));
        }

        return $cents;
    }

    /**
     * @throws DomainException
     */
    private function taxRate(mixed $value): string
    {
        if ($value === null || $value === '') {
            // Sin tasa explicita se usa la del sistema (config/quotes.php).
            return number_format((float) config('quotes.tax_rate'), 2, '.', '');
        }

        try {
            $hundredths = Money::rateToHundredths($value);
        } catch (InvalidArgumentException) {
            throw new DomainException(__('quotes.invalid_tax_rate'));
        }

        // Una tasa negativa no es un descuento: un total con signo en un
        // documento comercial seria peor que un error. El formulario ya lo
        // rechaza con min:0, aqui se repite porque esta es la frontera del
        // dominio.
        if ($hundredths < 0) {
            throw new DomainException(__('quotes.invalid_tax_rate'));
        }

        return (string) $value;
    }

    /**
     * El total podria no caber en decimal(15, 2) con importes muy grandes. Se
     * comprueba aqui para devolver un mensaje entendible y no un error de base
     * de datos al guardar.
     *
     * @throws DomainException
     */
    private function guardTotal(int $totalCents): void
    {
        if ($totalCents > Money::toCents(config('quotes.totals_max'))) {
            throw new DomainException(__('quotes.total_too_large'));
        }
    }
}
