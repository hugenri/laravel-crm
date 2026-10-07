const dateFormatter = new Intl.DateTimeFormat('es-ES', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
});

const dateTimeFormatter = new Intl.DateTimeFormat('es-ES', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

/*
 * Formatters para fechas sin hora (AAAA-MM-DD).
 */
const monthYearFormatter = new Intl.DateTimeFormat('es-ES', {
    month: 'long',
    year: 'numeric',
});

const longDateFormatter = new Intl.DateTimeFormat('es-ES', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

const weekdayFormatter = new Intl.DateTimeFormat('es-ES', {
    weekday: 'short',
});

const dayMonthFormatter = new Intl.DateTimeFormat('es-ES', {
    day: 'numeric',
    month: 'short',
});

/**
 * Montos en MXN. Los importes llegan como cadenas con decimales.
 */
const currencyFormatter = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

export function formatCurrency(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return currencyFormatter.format(Number(value));
}

/**
 * Fecha ISO 8601 de Eloquent a fecha corta según el locale. Acepta vacío
 * porque los timestamps de verificación y registro pueden no venir.
 */
export function formatDate(value) {
    if (!value) {
        return '—';
    }

    return dateFormatter.format(new Date(value));
}

export function formatDateTime(value) {
    if (!value) {
        return '—';
    }

    return dateTimeFormatter.format(new Date(value));
}

/**
 * Convierte fecha AAAA-MM-DD a Date local (evita offset UTC).
 */
export function parseDateOnly(value) {
    if (!value) {
        return null;
    }

    const [year, month, day] = String(value).split('-').map(Number);

    return new Date(year, month - 1, day);
}

/** "2026-10" -> "Octubre de 2026". */
export function formatMonthYear(value) {
    return capitalize(monthYearFormatter.format(parseDateOnly(value)));
}

/** "2026-10-05" -> "Lunes 5 de octubre de 2026". */
export function formatLongDate(value) {
    return capitalize(longDateFormatter.format(parseDateOnly(value)));
}

/** "2026-10-05" -> "Lun". */
export function formatWeekdayShort(value) {
    return capitalize(weekdayFormatter.format(parseDateOnly(value)).replace(/\.$/, ''));
}

/** "2026-10-05" -> "5". */
export function formatDayOfMonth(value) {
    return String(parseDateOnly(value).getDate());
}

/**
 * Rango de una semana, del lunes al domingo. El año se escribe una sola vez
 * salvo cuando la semana cruza diciembre: ahí se repite para no dejar ambiguo
 * el encabezado.
 */
export function formatWeekRange(start, end) {
    const from = parseDateOnly(start);
    const to = parseDateOnly(end);

    if (from.getFullYear() === to.getFullYear()) {
        return `${dayMonthFormatter.format(from)} – ${dayMonthFormatter.format(to)} ${to.getFullYear()}`;
    }

    return `${dayMonthFormatter.format(from)} ${from.getFullYear()} – ${dayMonthFormatter.format(to)} ${to.getFullYear()}`;
}

function capitalize(value) {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

