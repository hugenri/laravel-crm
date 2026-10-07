/**
 * Espejo de App\Support\Money para vista previa en cotizaciones.
 * Usa aritmética con enteros para evitar errores de punto flotante.
 * El cálculo real lo hace el servidor.
 */

/**
 * Decimal a centavos (enteros). Null si no es válido.
 */
export function toCents(value) {
    return scaled(value, 2);
}

/**
 * Decimal a milésimas (enteros). Permite hasta 3 decimales.
 */
export function toThousandths(value) {
    return scaled(value, 3);
}

function scaled(value, decimals) {
    const cleaned = String(value ?? '').replace(/[,\s ]/g, '');

    if (!/^\d+(\.\d+)?$/.test(cleaned)) {
        return null;
    }

    const [whole, fraction = ''] = cleaned.split('.');
    const kept = fraction.slice(0, decimals).padEnd(decimals, '0');
    const discarded = fraction.slice(decimals);

    const total = Number(whole) * 10 ** decimals + Number(kept);

    // Redondeo al alza cuando lo descartado >= 5, como en App\Support\Money.
    return discarded !== '' && Number(discarded[0]) >= 5 ? total + 1 : total;
}

/**
 * Importe de partida en centavos. Redondea al final.
 */
export function lineAmount(quantity, unitPrice) {
    const thousandths = toThousandths(quantity);
    const cents = toCents(unitPrice);

    if (thousandths === null || cents === null) {
        return null;
    }

    return Math.floor((thousandths * cents + 500) / 1000);
}

/**
 * Impuesto en centavos a partir de tasa (%) y subtotal (centavos).
 */
export function taxAmount(subtotalCents, taxRate) {
    const rate = toCents(taxRate);

    if (subtotalCents === null || rate === null) {
        return null;
    }

    return Math.floor((subtotalCents * rate + 5000) / 10000);
}

/**
 * Centavos a texto con 2 decimales.
 */
export function fromCents(cents) {
    if (cents === null) {
        return null;
    }

    return (cents / 100).toFixed(2);
}
