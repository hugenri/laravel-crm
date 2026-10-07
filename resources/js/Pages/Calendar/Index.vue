<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Badge from '../../Components/ui/Badge.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import FormField from '../../Components/ui/FormField.vue';
import PageHeader from '../../Components/PageHeader.vue';
import Select from '../../Components/ui/Select.vue';
import TextInput from '../../Components/ui/TextInput.vue';
import {
    formatDayOfMonth,
    formatLongDate,
    formatMonthYear,
    formatWeekRange,
    formatWeekdayShort,
    parseDateOnly,
} from '../../lib/format';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    range: { type: Object, required: true },
    views: { type: Object, required: true },
    types: { type: Object, required: true },
    statuses: { type: Object, required: true },
    events: { type: Array, required: true },
    filters: { type: Object, required: true },
    clients: { type: Array, required: true },
    users: { type: Array, required: true },
    opportunities: { type: Array, required: true },
    can: { type: Object, required: true },
});

/*
 * Máximo de chips a mostrar por celda. En vista día se muestran todos.
 */
const CHIPS_PER_DAY = 3;

const view = ref(props.filters.view);
const anchor = ref(props.filters.anchor);

const type = ref(props.filters.type ?? '');
const status = ref(props.filters.status ?? '');
const userId = ref(props.filters.user_id ?? '');
const mine = ref(Boolean(props.filters.mine));
const clientId = ref(props.filters.client_id ?? '');
const opportunityId = ref(props.filters.opportunity_id ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

// Días con la celda desplegada para ver todas sus actividades.
const expanded = ref([]);

const firstRender = ref(true);
let debounce;

watch([type, status, userId, mine, clientId, opportunityId, dateFrom, dateTo], () => {
    if (firstRender.value) {
        firstRender.value = false;

        return;
    }

    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 300);
});

/*
 * Sincroniza vista/ancla con lo que devuelve el backend.
 * Los filtros se mantienen intactos.
 */
watch(
    () => [props.range.view, props.range.anchor],
    () => {
        view.value = props.range.view;
        anchor.value = props.range.anchor;
        expanded.value = [];
    },
);

function currentQuery() {
    return {
        view: view.value,
        anchor: anchor.value,
        type: type.value,
        status: status.value,
        user_id: userId.value,
        mine: mine.value ? '1' : '',
        client_id: clientId.value,
        opportunity_id: opportunityId.value,
        date_from: dateFrom.value,
        date_to: dateTo.value,
    };
}

function applyFilters() {
    router.get(routes.calendar.index(currentQuery()), {}, {
        preserveState: true,
        replace: true,
    });
}

/**
 * Navegación con historial (atrás/adelante). Conserva el estado.
 */
function goTo(anchorValue) {
    router.get(routes.calendar.index({ ...currentQuery(), anchor: anchorValue }), {}, {
        preserveState: true,
    });
}

function switchView(key) {
    // Mantener ancla al cambiar de vista.
    router.get(routes.calendar.index({ ...currentQuery(), view: key }), {}, {
        preserveState: true,
    });
}

function resetFilters() {
    type.value = '';
    status.value = '';
    userId.value = '';
    mine.value = false;
    clientId.value = '';
    opportunityId.value = '';
    dateFrom.value = '';
    dateTo.value = '';
}

const hasFilters = computed(() =>
    Boolean(
        type.value || status.value || userId.value || mine.value || clientId.value
        || opportunityId.value || dateFrom.value || dateTo.value,
    ),
);

const label = computed(() => {
    if (props.range.view === 'week') {
        return formatWeekRange(props.range.start, props.range.end);
    }

    if (props.range.view === 'day') {
        return formatLongDate(props.range.anchor);
    }

    return formatMonthYear(props.range.anchor);
});

/**
 * Agrupa días en semanas para el grid.
 */
const weeks = computed(() => {
    if (props.range.view === 'day') {
        return [];
    }

    const rows = [];

    for (let index = 0; index < props.range.days.length; index += 7) {
        rows.push(props.range.days.slice(index, index + 7));
    }

    return rows;
});

const weekdayLabels = computed(() => props.range.days.slice(0, 7).map(formatWeekdayShort));

const eventsByDay = computed(() => {
    const grouped = new Map();

    props.events.forEach((event) => {
        const bucket = grouped.get(event.date);

        if (bucket) {
            bucket.push(event);
        } else {
            grouped.set(event.date, [event]);
        }
    });

    return grouped;
});

const dayEvents = computed(() => eventsByDay.value.get(props.range.anchor) ?? []);

function eventsOn(day) {
    return eventsByDay.value.get(day) ?? [];
}

function visibleEventsOn(day) {
    const events = eventsOn(day);

    return expanded.value.includes(day) ? events : events.slice(0, CHIPS_PER_DAY);
}

function hiddenOn(day) {
    if (expanded.value.includes(day)) {
        return 0;
    }

    return Math.max(0, eventsOn(day).length - CHIPS_PER_DAY);
}

function toggleDay(day) {
    expanded.value = expanded.value.includes(day)
        ? expanded.value.filter((value) => value !== day)
        : [...expanded.value, day];
}

function isOutsideMonth(day) {
    if (props.range.view !== 'month') {
        return false;
    }

    const current = parseDateOnly(props.range.anchor);
    const other = parseDateOnly(day);

    return current.getMonth() !== other.getMonth() || current.getFullYear() !== other.getFullYear();
}

function isToday(day) {
    return day === props.range.today;
}

function createOn(day) {
    return routes.activities.create({ activity_date: `${day}T00:00` });
}

/**
 * URL para alta desde cabecera: solo con fecha en vista día.
 */
const headerCreateUrl = computed(() =>
    props.range.view === 'day' ? createOn(props.range.anchor) : routes.activities.create(),
);

function statusTone(statusValue) {
    if (statusValue === 'completada') {
        return 'green';
    }

    if (statusValue === 'cancelada') {
        return 'red';
    }

    return 'amber';
}

const chipTones = {
    pendiente: 'bg-amber-50 text-amber-900 ring-amber-200 hover:bg-amber-100',
    completada: 'bg-emerald-50 text-emerald-900 ring-emerald-200 hover:bg-emerald-100',
    cancelada: 'bg-red-50 text-red-900 ring-red-200 hover:bg-red-100 line-through',
};
</script>

<template>
    <Head title="Calendario" />

    <PageHeader
        title="Calendario"
        description="Las mismas actividades del seguimiento, colocadas por día. No es un módulo aparte: lo que se ve aquí es lo que hay en Actividades."
    >
        <template #actions>
            <Link v-if="can.create" :href="headerCreateUrl">
                <Button type="button">Nueva actividad</Button>
            </Link>
            <Link :href="routes.activities.index()">
                <Button type="button" variant="secondary">Ver listado</Button>
            </Link>
        </template>
    </PageHeader>

    <div class="space-y-5">
        <Card>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <FormField for="type" label="Tipo">
                    <Select id="type" v-model="type">
                        <option value="">Todos los tipos</option>
                        <option v-for="(optionLabel, key) in types" :key="key" :value="key">
                            {{ optionLabel }}
                        </option>
                    </Select>
                </FormField>

                <FormField for="status" label="Estado">
                    <Select id="status" v-model="status">
                        <option value="">Todos los estados</option>
                        <option v-for="(optionLabel, key) in statuses" :key="key" :value="key">
                            {{ optionLabel }}
                        </option>
                    </Select>
                </FormField>

                <FormField for="user_id" label="Responsable">
                    <Select id="user_id" v-model="userId">
                        <option value="">Todos los responsables</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }}
                        </option>
                    </Select>
                </FormField>

                <FormField for="client_id" label="Cliente">
                    <Select id="client_id" v-model="clientId">
                        <option value="">Todos los clientes</option>
                        <option v-for="client in clients" :key="client.id" :value="client.id">
                            {{ client.name }}
                        </option>
                    </Select>
                </FormField>

                <FormField for="opportunity_id" label="Oportunidad">
                    <Select id="opportunity_id" v-model="opportunityId">
                        <option value="">Todas las oportunidades</option>
                        <option v-for="opportunity in opportunities" :key="opportunity.id" :value="opportunity.id">
                            {{ opportunity.name }}
                        </option>
                    </Select>
                </FormField>

                <FormField for="date_from" label="Desde">
                    <TextInput id="date_from" v-model="dateFrom" type="date" />
                </FormField>

                <FormField for="date_to" label="Hasta">
                    <TextInput id="date_to" v-model="dateTo" type="date" />
                </FormField>

                <div class="flex items-end justify-between gap-3">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input
                            v-model="mine"
                            type="checkbox"
                            class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                        >
                        Solo mis actividades
                    </label>

                    <Button
                        v-if="hasFilters"
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="resetFilters"
                    >
                        Limpiar
                    </Button>
                </div>
            </div>
        </Card>

        <Card>
            <template #header>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            :aria-label="`Ver ${range.previous}`"
                            @click="goTo(range.previous)"
                        >
                            ‹
                        </Button>

                        <Button type="button" variant="secondary" size="sm" @click="goTo(range.today)">
                            Hoy
                        </Button>

                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            :aria-label="`Ver ${range.next}`"
                            @click="goTo(range.next)"
                        >
                            ›
                        </Button>

                        <h2 class="ml-2 text-base font-semibold capitalize text-slate-900">
                            {{ label }}
                        </h2>
                    </div>

                    <div class="flex items-center gap-1 rounded-lg bg-slate-100 p-1">
                        <button
                            v-for="(optionLabel, key) in views"
                            :key="key"
                            type="button"
                            class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                            :class="key === range.view
                                ? 'bg-white text-slate-900 shadow-sm'
                                : 'text-slate-600 hover:text-slate-900'"
                            @click="switchView(key)"
                        >
                            {{ optionLabel }}
                        </button>
                    </div>
                </div>
            </template>

            <div v-if="range.view === 'day'" class="space-y-3">
                <p class="text-sm text-slate-500">
                    {{ dayEvents.length }}
                    {{ dayEvents.length === 1 ? 'actividad' : 'actividades' }}
                    en el día.
                </p>

                <p v-if="!dayEvents.length" class="rounded-lg bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                    No hay actividades para este día con los filtros aplicados.
                </p>

                <ul v-else class="divide-y divide-slate-200 rounded-lg ring-1 ring-slate-200">
                    <li v-for="event in dayEvents" :key="event.id">
                        <Link
                            :href="event.url"
                            class="flex flex-wrap items-center gap-3 px-4 py-3 transition hover:bg-slate-50"
                        >
                            <span class="w-14 shrink-0 text-sm font-medium tabular-nums text-slate-500">
                                {{ event.all_day ? 'Todo el día' : event.time }}
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-slate-900">
                                    {{ event.title }}
                                </span>
                                <span class="mt-0.5 block truncate text-xs text-slate-500">
                                    {{ event.client?.name }}
                                    <template v-if="event.opportunity">
                                        · {{ event.opportunity.name }}
                                    </template>
                                    <template v-if="event.user">
                                        · {{ event.user.name }}
                                    </template>
                                </span>
                            </span>

                            <Badge :tone="statusTone(event.status)">
                                {{ event.status_label }}
                            </Badge>
                            <Badge>{{ event.type_label }}</Badge>
                        </Link>
                    </li>
                </ul>
            </div>

            <div v-else>
                <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50">
                    <div
                        v-for="(weekday, index) in weekdayLabels"
                        :key="index"
                        class="px-2 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-500"
                    >
                        {{ weekday }}
                    </div>
                </div>

                <div
                    v-for="(week, weekIndex) in weeks"
                    :key="weekIndex"
                    class="grid grid-cols-7 border-b border-slate-200 last:border-b-0"
                >
                    <div
                        v-for="day in week"
                        :key="day"
                        class="min-h-24 border-r border-slate-200 p-1.5 last:border-r-0"
                        :class="isOutsideMonth(day) ? 'bg-slate-50' : ''"
                    >
                        <div class="mb-1 flex items-center justify-between gap-1">
                            <span
                                class="grid h-6 w-6 place-items-center rounded-full text-xs font-medium"
                                :class="isToday(day)
                                    ? 'bg-brand-600 text-white'
                                    : isOutsideMonth(day)
                                        ? 'text-slate-400'
                                        : 'text-slate-700'"
                            >
                                {{ formatDayOfMonth(day) }}
                            </span>

                            <Link
                                v-if="can.create"
                                :href="createOn(day)"
                                class="text-xs text-slate-400 transition hover:text-brand-600"
                                :aria-label="`Nueva actividad el ${day}`"
                            >
                                +
                            </Link>
                        </div>

                        <div class="space-y-1">
                            <Link
                                v-for="event in visibleEventsOn(day)"
                                :key="event.id"
                                :href="event.url"
                                class="block truncate rounded px-1.5 py-0.5 text-xs ring-1 ring-inset"
                                :class="chipTones[event.status] ?? chipTones.pendiente"
                                :title="`${event.type_label} · ${event.client?.name ?? ''}`"
                            >
                                <span v-if="!event.all_day" class="font-medium tabular-nums">
                                    {{ event.time }}
                                </span>
                                <span>{{ event.title }}</span>
                            </Link>

                            <button
                                v-if="hiddenOn(day)"
                                type="button"
                                class="px-1 text-xs font-medium text-brand-600 hover:text-brand-700"
                                @click="toggleDay(day)"
                            >
                                +{{ hiddenOn(day) }} más
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Card>
    </div>
</template>
