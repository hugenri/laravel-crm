<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Valida el formato del RFC mexicano según el tipo de cliente. Solo rechaza
 * claramente inválidos, que es lo que pide el alcance de la fase: sin
 * consultar al SAT, la regla es puramente local y no mete dependencias.
 *
 *   - Persona física: 13 caracteres. 4 iniciales, 6 de fecha, 3 de homoclave.
 *     Ejemplo: GODE561231GR8
 *   - Persona moral: 12 caracteres. 3 iniciales, 6 de fecha, 3 de homoclave.
 *     Ejemplo: GSO101010ABC
 *
 * El ampersand y la ene valen en las iniciales; la homoclave admite letras y
 * números. La fecha se comprueba contra rangos plausibles (mes 01-12, día
 * 01-31) y no contra el calendario real, para no rechazar un RFC legítimo con
 * fecha atípica.
 */
class Rfc implements ValidationRule
{
    /**
     * Los grupos son año, mes y día. Extraerlos con subcadenas obligaría a
     * calcular la posición a mano, y esa posición depende de cuántas iniciales
     * tiene el tipo, así que el propio patrón dice dónde está cada parte.
     *
     * @var array<string, string>
     */
    private const PATTERNS = [
        'persona' => '/^[A-Z&Ñ]{4}(\d{2})(\d{2})(\d{2})[A-Z0-9]{3}$/',
        'empresa' => '/^[A-Z&Ñ]{3}(\d{2})(\d{2})(\d{2})[A-Z0-9]{3}$/',
    ];

    public function __construct(private readonly string $type) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $rfc = Str::upper(trim($value));

        $pattern = self::PATTERNS[$this->type] ?? self::PATTERNS['empresa'];

        if (preg_match($pattern, $rfc, $matches) !== 1) {
            $fail($this->message($this->type));

            return;
        }

        [, , $month, $day] = $matches;

        if ((int) $month < 1 || (int) $month > 12) {
            $fail(__('validation.rfc_month'));

            return;
        }

        if ((int) $day < 1 || (int) $day > 31) {
            $fail(__('validation.rfc_day'));
        }
    }

    private function message(string $type): string
    {
        return $type === 'persona'
            ? __('validation.rfc_person')
            : __('validation.rfc_company');
    }
}
