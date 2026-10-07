<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Badge from '../../Components/ui/Badge.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import PageHeader from '../../Components/PageHeader.vue';
import Select from '../../Components/ui/Select.vue';
import TextInput from '../../Components/ui/TextInput.vue';
import { formatCurrency, formatDate } from '../../lib/format';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    quotes: { type: Object, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Object, required: true },
    sortable: { type: Array, required: true },
    clients: { type: Array, required: true },
    users: { type: Array, required: true },
    can: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const clientId = ref(props.filters.client_id ?? '');
const userId = ref(props.filters.user_id ?? '');
const sort = ref(props.filters.sort);
const direction = ref(props.filters.direction);

const firstRender = ref(true);
let debounce;

watch([search, status, clientId, userId], () => {
    if (firstRender.value) {
        firstRender.value = false;

        return;
    }

    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 300);
});

watch([sort, direction], applyFilters);

function applyFilters() {
    router.get(
        routes.quotes.index({
            search: search.value,
            status: status.value,
            client_id: clientId.value,
            user_id: userId.value,
            sort: sort.value,
            direction: direction.value,
        }),
        {},
        { preserveState: true, replace: true },
    );
}

function toggleSort(column) {
    if (sort.value === column) {
        direction.value = direction.value === 'asc' ? 'desc' : 'asc';

        return;
    }

    sort.value = column;
    direction.value = 'asc';
}

function resetFilters() {
    search.value = '';
    status.value = '';
    clientId.value = '';
    userId.value = '';
}

function sortIndicator(column) {
    if (sort.value !== column) {
        return '';
    }

    return direction.value === 'asc' ? '↑' : '↓';
}

const hasFilters = computed(() => Boolean(search.value || status.value || clientId.value || userId.value));
const from = computed(() => props.quotes.meta?.from ?? 0);
const to = computed(() => props.quotes.meta?.to ?? 0);
const total = computed(() => props.quotes.meta?.total ?? 0);

/**
 * La columna muestra el estado efectivo: el guardado, salvo que la vigencia ya
 * haya vencido, en cuyo caso se muestra como vencida.
 */
function statusTone(statusValue) {
    if (statusValue === 'aceptada') {
        return 'green';
    }

    if (statusValue === 'rechazada' || statusValue === 'vencida') {
        return 'red';
    }

    if (statusValue === 'enviada') {
        return 'brand';
    }

    return 'slate';
}

const columns = [
    { key: 'folio', label: 'Folio' },
    { key: 'client', label: 'Cliente', sortable: false },
    { key: 'user', label: 'Responsable', sortable: false },
    { key: 'status', label: 'Estado' },
    { key: 'issue_date', label: 'Emisión' },
    { key: 'valid_until', label: 'Válida hasta' },
    { key: 'total', label: 'Total' },
];
</script>

<template>
    <Head title="Cotizaciones" />

    <PageHeader title="Cotizaciones" description="Propuestas comerciales enviadas a los clientes.">
        <template #actions>
            <Link v-if="can.create" :href="routes.quotes.create()">
                <Button type="button">Nueva cotización</Button>
            </Link>
        </template>
    </PageHeader>

    <Card>
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-56 flex-1">
                <label for="search" class="block text-sm font-medium text-slate-700">Buscar</label>
                <TextInput
                    id="search"
                    v-model="search"
                    class="mt-1.5"
                    type="search"
                    placeholder="Folio o nombre del cliente"
                />
            </div>

            <div class="w-44">
                <label for="status" class="block text-sm font-medium text-slate-700">Estado</label>
                <Select id="status" v-model="status" class="mt-1.5">
                    <option value="">Todos los estados</option>
                    <option v-for="(label, key) in statuses" :key="key" :value="key">
                        {{ label }}
                    </option>
                </Select>
            </div>

            <div class="w-56">
                <label for="client_id" class="block text-sm font-medium text-slate-700">Cliente</label>
                <Select id="client_id" v-model="clientId" class="mt-1.5">
                    <option value="">Todos los clientes</option>
                    <option v-for="client in clients" :key="client.id" :value="client.id">
                        {{ client.name }}
                    </option>
                </Select>
            </div>

            <div class="w-48">
                <label for="user_id" class="block text-sm font-medium text-slate-700">Responsable</label>
                <Select id="user_id" v-model="userId" class="mt-1.5">
                    <option value="">Todos los responsables</option>
                    <option v-for="user in users" :key="user.id" :value="user.id">
                        {{ user.name }}
                    </option>
                </Select>
            </div>

            <Button v-if="hasFilters" type="button" variant="ghost" @click="resetFilters">
                Limpiar
            </Button>
        </div>
    </Card>

    <div class="mt-5 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            scope="col"
                            class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            <button
                                v-if="column.sortable !== false && sortable.includes(column.key)"
                                type="button"
                                class="inline-flex items-center gap-1 hover:text-slate-800"
                                @click="toggleSort(column.key)"
                            >
                                {{ column.label }}
                                <span aria-hidden="true">{{ sortIndicator(column.key) }}</span>
                            </button>
                            <span v-else>{{ column.label }}</span>
                        </th>
                        <th
                            scope="col"
                            class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            Acciones
                        </th>
                    </tr>
                </thead>

                <tbody v-if="quotes.data.length" class="divide-y divide-slate-100">
                    <tr v-for="quote in quotes.data" :key="quote.id" class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-5 py-3 text-sm font-medium text-slate-900">
                            <Link :href="routes.quotes.show(quote.id)" class="hover:text-brand-700">
                                {{ quote.folio }}
                            </Link>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">
                            <Link
                                v-if="quote.client"
                                :href="routes.clients.show(quote.client.id)"
                                class="hover:text-brand-700"
                            >
                                {{ quote.client.name }}
                            </Link>
                            <span v-else>—</span>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">
                            {{ quote.user?.name ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-3">
                            <Badge :tone="statusTone(quote.display_status)">
                                {{ quote.display_status_label ?? quote.display_status }}
                            </Badge>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">
                            {{ formatDate(quote.issue_date) }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">
                            {{ formatDate(quote.valid_until) }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-900">
                            {{ formatCurrency(quote.total) }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-right">
                            <div class="inline-flex items-center gap-2">
                                <Link :href="routes.quotes.show(quote.id)">
                                    <Button type="button" variant="secondary" size="sm">Ver</Button>
                                </Link>
                                <!--
                                    El PDF se descarga con una recarga normal,
                                    no con una petición de Inertia: el servidor
                                    responde con el archivo, no con una página.
                                    Por eso no lleva un Button dentro, que sería
                                    contenido interactivo anidado en un enlace.
                                -->
                                <a
                                    v-if="can.pdf"
                                    :href="routes.quotes.pdf(quote.id)"
                                    class="inline-flex items-center justify-center rounded-lg bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 ring-1 ring-inset ring-slate-300 transition hover:bg-slate-50"
                                >
                                    PDF
                                </a>
                            </div>
                        </td>
                    </tr>
                </tbody>

                <tbody v-else>
                    <tr>
                        <td
                            :colspan="columns.length + 1"
                            class="px-5 py-10 text-center text-sm text-slate-500"
                        >
                            No hay cotizaciones que coincidan con los criterios indicados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-3">
            <p class="text-sm text-slate-500">Mostrando {{ from }}–{{ to }} de {{ total }}</p>

            <div v-if="quotes.links.length > 3" class="flex items-center gap-1">
                <Link
                    v-for="link in quotes.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    class="rounded-lg px-3 py-1.5 text-sm"
                    :class="[
                        link.active ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-100',
                        !link.url ? 'pointer-events-none opacity-40' : '',
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>
    </div>
</template>
