<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Aritmética de importes en enteros.
 *
 * Los totales de una cotización no se calculan con float: un doble no
 * representa 0.10 ni 19.99 y con cinco líneas ya se pierde precisión, que es
 * lo que el cliente compara contra el PDF. El dinero pasa a céntimos enteros
 * antes de multiplicar y vuelve a cadena al guardar, para que la suma de las
 * partidas sea siempre exactamente el subtotal.
 *
 * Las cantidades van en milésimas porque la columna "quantity" admite tres
 * decimales: 2.5 horas no puede perder el .5.
 *
 * Todo importe que pasa por aquí es no negativo: uno negativo sería un
 * descuento, y los descuentos quedan fuera de la primera versión.
 */
final class Money
{
    /**
     * Redondeo al céntimo, mitad hacia arriba, la regla que esperan los
     * clientes y las hojas de cálculo.
     */
    private const HALF = 500;

    /**
     * Cadena decimal con signo opcional. No is_numeric, para que "1e5" y
     * "0x1A" queden fuera: no son decimales que el módulo sepa interpretar.
     */
    private const DECIMAL = '/^-?\d+(\.\d+)?$/';

    /**
     * @param  string|int|float  $value  Cadena decimal en pesos. Se acepta float
     *                                   porque el casting decimal de Eloquent
     *                                   devuelve "12.50" y no un float.
     *
     * @throws InvalidArgumentException
     */
    public static function toCents(string|int|float $value): int
    {
        [$whole, $fraction] = self::split($value);

        return $whole * 100
            + (int) str_pad(substr($fraction, 0, 2), 2, '0')
            + self::roundUp(substr($fraction, 2));
    }

    /**
     * Centimos a la cadena con dos decimales que espera la columna decimal.
     * Sin dividir: intdiv y el resto dan el resultado exacto sin meter un
     * float en la ecuación.
     */
    public static function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Milésimas a la cadena con tres decimales que espera la columna
     * "quantity".
     */
    public static function fromThousandths(int $thousandths): string
    {
        $sign = $thousandths < 0 ? '-' : '';
        $absolute = abs($thousandths);

        return $sign.intdiv($absolute, 1000).'.'.str_pad((string) ($absolute % 1000), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Importe de una partida: cantidad por precio unitario, con los valores
     * tal como los captura el usuario. Quien ya los tenga convertidos usa
     * lineAmountInCents(): aquí un entero que ya son milésimas se convertiría
     * otra vez.
     *
     * Se multiplica en enteros y se redondea una sola vez, al final: redondear
     * el precio unitario antes daría un importe distinto al de la pantalla.
     *
     * @param  string|int|float  $quantity  Cantidad en unidades
     * @param  string|int|float  $unitPrice  Precio unitario en pesos
     *
     * @throws InvalidArgumentException
     */
    public static function lineAmount(string|int|float $quantity, string|int|float $unitPrice): int
    {
        return self::lineAmountInCents(self::toThousandths($quantity), self::toCents($unitPrice));
    }

    /**
     * Importe de una partida en céntimos, con la cantidad ya en milésimas y el
     * precio ya en céntimos. El divisor es 1000 por las unidades de cada uno;
     * el redondeo del término medio decide si la tercera cifra de la cantidad
     * empuja el importe un céntimo.
     */
    public static function lineAmountInCents(int $thousandths, int $cents): int
    {
        return intdiv($thousandths * $cents + self::HALF, 1000);
    }

    /**
     * Tasa de impuesto en céntimos de porcentaje: se guarda como "16.00" y
     * aquí se vuelve 1600, para no multiplicar por un float (el impuesto de
     * 1234.56 al 16% es 197.53, no 197.5296).
     *
     * @throws InvalidArgumentException
     */
    public static function rateToHundredths(string|int|float $rate): int
    {
        return self::toCents($rate);
    }

    /**
     * Impuesto sobre un subtotal, en céntimos. La tasa va en céntimos de
     * porcentaje, así que el divisor es 10000 (subtotal * tasa * 100 / 10000)
     * y el redondeo del término medio se escala por diez.
     *
     * @throws InvalidArgumentException
     */
    public static function tax(int $subtotalCents, int $rateHundredths): int
    {
        if ($subtotalCents < 0 || $rateHundredths < 0) {
            throw new InvalidArgumentException('El impuesto no se calcula sobre importes negativos.');
        }

        return intdiv($subtotalCents * $rateHundredths + (self::HALF * 10), 10000);
    }

    /**
     * Cantidad a milésimas enteras, con el mismo redondeo que toCents.
     *
     * @throws InvalidArgumentException
     */
    public static function toThousandths(string|int|float $value): int
    {
        [$whole, $fraction] = self::split($value);

        return $whole * 1000
            + (int) str_pad(substr($fraction, 0, 3), 3, '0')
            + self::roundUp(substr($fraction, 3));
    }

    /**
     * Entero y fracción ya normalizados, como cadenas.
     *
     * @return array{int, string}
     *
     * @throws InvalidArgumentException
     */
    private static function split(string|int|float $value): array
    {
        $cleaned = is_int($value)
            ? (string) $value
            : str_replace([',', ' ', "\u{00A0}", "\u{2009}"], '', trim((string) $value));

        if (preg_match(self::DECIMAL, $cleaned) !== 1) {
            throw new InvalidArgumentException("El importe [{$value}] no es un número válido.");
        }

        $negative = str_starts_with($cleaned, '-');

        $parts = explode('.', ltrim($cleaned, '-'), 2);

        $whole = (int) $parts[0];

        return [$negative ? -$whole : $whole, $parts[1] ?? ''];
    }

    /**
     * Uno si los decimales descartados suben el valor medio céntimo, cero si
     * no. Se decide por el primer dígito descartado y no por una comparación,
     * porque el valor ya es una cadena exacta y no tiene un float detrás.
     */
    private static function roundUp(string $discarded): int
    {
        return $discarded !== '' && $discarded[0] >= '5' ? 1 : 0;
    }
}
