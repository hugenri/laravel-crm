<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import FormField from '../../Components/ui/FormField.vue';
import PageHeader from '../../Components/PageHeader.vue';
import Select from '../../Components/ui/Select.vue';
import StatCard from '../../Components/ui/StatCard.vue';
import TextInput from '../../Components/ui/TextInput.vue';
import { formatCurrency, formatLongDate } from '../../lib/format';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

/**
 * Los cuatro bloques llegan como null sin permiso y el v-if los oculta,
 * igual que en el dashboard. Importes y porcentaje de conversión llegan
 * como cadenas y se formatean aquí con helpers de lib/format.
 */
const props = defineProps({
    opportunities: { type: Object, default: null },
    quotes: { type: Object, default: null },
    prospects: { type: Object, default: null },
    activities: { type: Object, default: null },
    byStage: { type: Array, default: null },
    byStatus: { type: Object, required: true },
    byResponsible: { type: Object, required: true },
    filters: { type: Object, required: true },
    users: { type: Array, required: true },
    can: { type: Object, required: true },
});

const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const userId = ref(props.filters.user_id ?? '');

const firstRender = ref(true);
let debounce;

/*
 * Filtros con debounce. No crean historial al cambiar (replace).
 */
watch([dateFrom, dateTo, userId], () => {
    if (firstRender.value) {
        firstRender.value = false;

        return;
    }

    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 300);
});

/**
 * Sincroniza filtros con la URL (recarga/enlace compartido).
 */
watch(
    () => [props.filters.date_from, props.filters.date_to, props.filters.user_id],
    () => {
        dateFrom.value = props.filters.date_from ?? '';
        dateTo.value = props.filters.date_to ?? '';
        userId.value = props.filters.user_id ?? '';
    },
);

function currentQuery() {
    return {
        date_from: dateFrom.value,
        date_to: dateTo.value,
        user_id: userId.value,
    };
}

function applyFilters() {
    router.get(routes.reports.index(currentQuery()), {}, {
        preserveState: true,
        replace: true,
    });
}

function resetFilters() {
    dateFrom.value = '';
    dateTo.value = '';
    userId.value = '';
}

const hasFilters = computed(() => Boolean(dateFrom.value || dateTo.value || userId.value));

const periodLabel = computed(() => {
    if (dateFrom.value && dateTo.value) {
        return `Del ${formatLongDate(dateFrom.value)} al ${formatLongDate(dateTo.value)}.`;
    }

    if (dateFrom.value) {
        return `Desde el ${formatLongDate(dateFrom.value)}.`;
    }

    if (dateTo.value) {
        return `Hasta el ${formatLongDate(dateTo.value)}.`;
    }

    return 'Historial completo.';
});

const responsibleLabel = computed(() => {
    if (!userId.value) {
        return 'Todos los responsables.';
    }

    const user = props.users.find((item) => String(item.id) === String(userId.value));

    return user ? `Responsable: ${user.name}.` : 'Un responsable.';
});

/**
 * Ancho de barra por etapa (mínimo 8% para valores bajos).
 */
const stageRows = computed(() => {
    const rows = props.byStage ?? [];
    const highest = Math.max(1, ...rows.map((row) => row.count));

    return rows.map((row) => ({
        ...row,
        width: row.count === 0 ? 0 : Math.max(8, Math.round((row.count / highest) * 100)),
    }));
});

/**
 * Filas de los tres reportes por responsable, cada una con su propia forma.
 */
const opportunitiesByResponsible = computed(() => props.byResponsible.opportunities ?? []);
const prospectsByResponsible = computed(() => props.byResponsible.prospects ?? []);
const activitiesByResponsible = computed(() => props.byResponsible.activities ?? []);

/**
 * Máximo monto abierto para escalar barras.
 */
const highestOpenAmount = computed(() => Math.max(
    1,
    ...opportunitiesByResponsible.value.map((row) => Number(row.openAmount)),
));

function opportunityWidth(row) {
    if (Number(row.openAmount) <= 0) {
        return 0;
    }

    return Math.max(8, Math.round((Number(row.openAmount) / highestOpenAmount.value) * 100));
}

function percentage(count, total) {
    if (!total) {
        return 0;
    }

    return Math.round((count / total) * 100);
}

/**
 * Enlaces a listados con filtros aplicables.
 */
const listings = computed(() => [
    {
        key: 'opportunities',
        label: 'Oportunidades',
        description: 'Las mismas oportunidades, en detalle.',
        href: routes.opportunities.index({ user_id: userId.value }),
    },
    {
        key: 'quotes',
        label: 'Cotizaciones',
        description: 'Las propuestas del periodo, con su folio.',
        href: routes.quotes.index({ user_id: userId.value }),
    },
    {
        key: 'prospects',
        label: 'Prospectos',
        description: 'El embudo uno por uno.',
        href: routes.prospects.index({ user_id: userId.value }),
    },
    {
        key: 'activities',
        label: 'Actividades',
        description: 'El seguimiento pendiente, con su fecha.',
        href: routes.activities.index(currentQuery()),
    },
]);

/**
 * Solo listados con permiso para ver el módulo.
 */
const visibleListings = computed(() => listings.value.filter((item) => props.can[item.key]));
</script>

<template>
    <Head title="Reportes" />

    <PageHeader
        title="Reportes"
        description="Resumen comercial por periodo y por responsable. Los números salen de los mismos datos que los listados y no se guardan."
    />

    <div class="space-y-5">
        <Card>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <FormField for="date_from" label="Desde">
                    <TextInput id="date_from" v-model="dateFrom" type="date" />
                </FormField>

                <FormField for="date_to" label="Hasta">
                    <TextInput id="date_to" v-model="dateTo" type="date" />
                </FormField>

                <FormField for="user_id" label="Responsable">
                    <Select id="user_id" v-model="userId">
                        <option value="">Todos los responsables</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }}
                        </option>
                    </Select>
                </FormField>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-500">
                    {{ periodLabel }} {{ responsibleLabel }}
                </p>

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
        </Card>

        <!--
            Oportunidades: los seis indicadores del periodo y, debajo, el embudo
            y el detalle por responsable. El bloque se oculta sin opportunities.view.
        -->
        <section v-if="opportunities">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
                Oportunidades
            </h2>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <StatCard label="Creadas en el periodo" :value="opportunities.total" />
                <StatCard label="Abiertas" :value="opportunities.open" tone="brand" />
                <StatCard label="Ganadas" :value="opportunities.won" tone="green" />
                <StatCard label="Perdidas" :value="opportunities.lost" tone="red" />
                <StatCard
                    label="Monto abierto"
                    :value="formatCurrency(opportunities.openAmount)"
                />
                <StatCard
                    label="Monto ganado"
                    :value="formatCurrency(opportunities.wonAmount)"
                    tone="green"
                />
            </div>

            <p class="mt-2 text-xs text-slate-500">
                El periodo cuenta por fecha de alta: ganadas y perdidas son las que hoy lo están, de las creadas en esas fechas.
            </p>
        </section>

        <div class="grid gap-5 lg:grid-cols-2">
            <Card
                v-if="byStage"
                title="Oportunidades por etapa"
                description="Todo el embudo, con las etapas vacías en cero."
            >
                <ul v-if="stageRows.length" class="space-y-3">
                    <li v-for="row in stageRows" :key="row.stage" class="flex items-center gap-3">
                        <span class="w-28 shrink-0 truncate text-sm text-slate-600">{{ row.label }}</span>
                        <span class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                            <span
                                class="block h-full rounded-full bg-brand-500"
                                :style="{ width: `${row.width}%` }"
                            />
                        </span>
                        <span class="w-8 shrink-0 text-right text-sm font-medium text-slate-900">
                            {{ row.count }}
                        </span>
                    </li>
                </ul>

                <p v-else class="py-6 text-center text-sm text-slate-500">
                    No hay etapas configuradas.
                </p>
            </Card>

            <Card
                v-if="opportunities"
                title="Oportunidades por responsable"
                description="Volumen, resultado y monto abierto de cada uno."
            >
                <div v-if="opportunitiesByResponsible.length" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Responsable</th>
                                <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Total</th>
                                <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Abiertas</th>
                                <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Ganadas</th>
                                <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Perdidas</th>
                                <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Monto abierto</th>
                                <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Monto ganado</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="row in opportunitiesByResponsible" :key="row.user_id ?? 'unassigned'">
                                <td class="px-3 py-3 text-sm text-slate-900">{{ row.name }}</td>
                                <td class="px-3 py-3 text-right text-sm text-slate-600">{{ row.total }}</td>
                                <td class="px-3 py-3 text-right text-sm text-slate-600">{{ row.open }}</td>
                                <td class="px-3 py-3 text-right text-sm text-emerald-700">{{ row.won }}</td>
                                <td class="px-3 py-3 text-right text-sm text-red-700">{{ row.lost }}</td>
                                <td class="px-3 py-3 text-right text-sm text-slate-600">
                                    <span class="block">{{ formatCurrency(row.openAmount) }}</span>
                                    <span class="mt-1 block h-1.5 overflow-hidden rounded-full bg-slate-100">
                                        <span
                                            class="block h-full rounded-full bg-brand-500"
                                            :style="{ width: `${opportunityWidth(row)}%` }"
                                        />
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-right text-sm text-slate-600">
                                    {{ formatCurrency(row.wonAmount) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-else class="py-6 text-center text-sm text-slate-500">
                    No hay oportunidades en el periodo.
                </p>
            </Card>
        </div>

        <section v-if="quotes">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
                Cotizaciones
            </h2>

            <div class="grid gap-5 sm:grid-cols-3">
                <StatCard label="Emitidas en el periodo" :value="quotes.total" />
                <StatCard label="Monto cotizado" :value="formatCurrency(quotes.totalAmount)" />
                <StatCard
                    label="Monto aceptado"
                    :value="formatCurrency(quotes.acceptedAmount)"
                    tone="green"
                />
            </div>
        </section>

        <div class="grid gap-5 lg:grid-cols-2">
            <Card
                v-if="byStatus.quotes"
                title="Cotizaciones por estado"
                description="Conteo y monto de cada estado, según la fecha de emisión."
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</th>
                                <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Cotizaciones</th>
                                <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Monto</th>
                                <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Del total</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="row in byStatus.quotes" :key="row.status">
                                <td class="px-3 py-3 text-sm text-slate-900">{{ row.label }}</td>
                                <td class="px-3 py-3 text-right text-sm text-slate-600">{{ row.count }}</td>
                                <td class="px-3 py-3 text-right text-sm text-slate-600">
                                    {{ formatCurrency(row.amount) }}
                                </td>
                                <td class="px-3 py-3 text-right text-sm text-slate-500">
                                    {{ percentage(row.count, quotes.total) }}%
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>

            <Card
                v-if="byStatus.prospects"
                title="Prospectos por estado"
                description="El embudo, con el estado convertido incluido."
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</th>
                                <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Prospectos</th>
                                <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Del total</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="row in byStatus.prospects" :key="row.status">
                                <td class="px-3 py-3 text-sm text-slate-900">{{ row.label }}</td>
                                <td class="px-3 py-3 text-right text-sm text-slate-600">{{ row.count }}</td>
                                <td class="px-3 py-3 text-right text-sm text-slate-500">
                                    {{ percentage(row.count, prospects.total) }}%
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>

        <section v-if="prospects">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
                Embudo de prospectos
            </h2>

            <div class="grid gap-5 sm:grid-cols-4">
                <StatCard label="Capturados en el periodo" :value="prospects.total" />
                <StatCard label="En el embudo" :value="prospects.open" tone="brand" />
                <StatCard label="Convertidos" :value="prospects.converted" tone="green" />
                <StatCard
                    label="Tasa de conversión"
                    :value="`${prospects.conversionRate}%`"
                    tone="green"
                />
            </div>
        </section>

        <Card
            v-if="prospectsByResponsible.length"
            title="Prospectos por responsable"
            description="Quién captura el embudo y cuánto de lo suyo llegó a convertirse."
            class="mt-5"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Responsable</th>
                            <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Prospectos</th>
                            <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">En el embudo</th>
                            <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Convertidos</th>
                            <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Tasa</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in prospectsByResponsible" :key="row.user_id ?? 'unassigned'">
                            <td class="px-3 py-3 text-sm text-slate-900">{{ row.name }}</td>
                            <td class="px-3 py-3 text-right text-sm text-slate-600">{{ row.total }}</td>
                            <td class="px-3 py-3 text-right text-sm text-slate-600">{{ row.open }}</td>
                            <td class="px-3 py-3 text-right text-sm text-slate-600">{{ row.converted }}</td>
                            <td class="px-3 py-3 text-right text-sm text-slate-500">
                                {{ row.conversionRate }}%
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <div class="grid gap-5 lg:grid-cols-2">
            <Card
                v-if="activities"
                title="Actividades pendientes"
                description="Las que siguen sin resolverse, por responsable."
            >
                <p class="mb-4 text-sm text-slate-500">
                    <span class="text-2xl font-semibold tracking-tight text-slate-900">{{ activities.pending }}</span>
                    pendientes en el periodo.
                </p>

                <div v-if="activitiesByResponsible.length" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Responsable</th>
                                <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Pendientes</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="row in activitiesByResponsible" :key="row.user_id ?? 'unassigned'">
                                <td class="px-3 py-3 text-sm text-slate-900">{{ row.name }}</td>
                                <td class="px-3 py-3 text-right text-sm text-slate-600">{{ row.count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-else class="py-6 text-center text-sm text-slate-500">
                    No hay actividades pendientes en el periodo.
                </p>
            </Card>

            <Card
                v-if="visibleListings.length"
                title="Consultar en los listados"
                description="Los reportes no se guardan: se vuelven a calcular desde los listados."
            >
                <ul class="space-y-3">
                    <li v-for="item in visibleListings" :key="item.key">
                        <Link
                            :href="item.href"
                            class="block rounded-lg border border-slate-200 p-3 transition hover:border-brand-300 hover:bg-brand-50/40"
                        >
                            <p class="text-sm font-semibold text-slate-900">{{ item.label }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ item.description }}</p>
                        </Link>
                    </li>
                </ul>

                <p class="mt-4 text-xs text-slate-500">
                    Solo el listado de actividades admite un rango de fechas, así que es el único enlace que lo arrastra.
                </p>
            </Card>
        </div>

        <div v-if="!opportunities && !quotes && !prospects && !activities" class="text-center">
            <p class="text-sm text-slate-500">
                No hay ningún módulo que puedas consultar desde aquí.
            </p>
        </div>
    </div>
</template>
