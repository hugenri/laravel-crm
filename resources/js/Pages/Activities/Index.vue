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
import { formatDateTime } from '../../lib/format';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    activities: { type: Object, required: true },
    filters: { type: Object, required: true },
    types: { type: Object, required: true },
    statuses: { type: Object, required: true },
    sortable: { type: Array, required: true },
    clients: { type: Array, required: true },
    users: { type: Array, required: true },
    opportunities: { type: Array, required: true },
    can: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
const type = ref(props.filters.type ?? '');
const status = ref(props.filters.status ?? '');
const userId = ref(props.filters.user_id ?? '');
const clientId = ref(props.filters.client_id ?? '');
const opportunityId = ref(props.filters.opportunity_id ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const sort = ref(props.filters.sort);
const direction = ref(props.filters.direction);

const firstRender = ref(true);
let debounce;

watch([search, type, status, userId, clientId, opportunityId, dateFrom, dateTo], () => {
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
        routes.activities.index({
            search: search.value,
            type: type.value,
            status: status.value,
            user_id: userId.value,
            client_id: clientId.value,
            opportunity_id: opportunityId.value,
            date_from: dateFrom.value,
            date_to: dateTo.value,
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
    type.value = '';
    status.value = '';
    userId.value = '';
    clientId.value = '';
    opportunityId.value = '';
    dateFrom.value = '';
    dateTo.value = '';
}

function sortIndicator(column) {
    if (sort.value !== column) {
        return '';
    }

    return direction.value === 'asc' ? '↑' : '↓';
}

const hasFilters = computed(() =>
    Boolean(
        search.value || type.value || status.value || userId.value || clientId.value
        || opportunityId.value || dateFrom.value || dateTo.value,
    ),
);
const from = computed(() => props.activities.meta?.from ?? 0);
const to = computed(() => props.activities.meta?.to ?? 0);
const total = computed(() => props.activities.meta?.total ?? 0);

function statusTone(status) {
    if (status === 'completada') {
        return 'green';
    }

    if (status === 'cancelada') {
        return 'red';
    }

    return 'amber';
}

const columns = [
    { key: 'activity_date', label: 'Fecha' },
    { key: 'type', label: 'Tipo' },
    { key: 'client', label: 'Cliente', sortable: false },
    { key: 'opportunity', label: 'Oportunidad', sortable: false },
    { key: 'user', label: 'Responsable', sortable: false },
    { key: 'status', label: 'Estado' },
    { key: 'description', label: 'Descripción', sortable: false },
];
</script>

<template>
    <Head title="Actividades" />

    <PageHeader
        title="Actividades"
        description="Seguimiento registrado con clientes y oportunidades."
    >
        <template #actions>
            <Link v-if="can.create" :href="routes.activities.create()">
                <Button type="button">Nueva actividad</Button>
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
                    placeholder="Descripción o nombre del cliente"
                />
            </div>

            <div class="w-40">
                <label for="type" class="block text-sm font-medium text-slate-700">Tipo</label>
                <Select id="type" v-model="type" class="mt-1.5">
                    <option value="">Todos los tipos</option>
                    <option v-for="(label, key) in types" :key="key" :value="key">
                        {{ label }}
                    </option>
                </Select>
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

            <div class="w-48">
                <label for="user_id" class="block text-sm font-medium text-slate-700">Responsable</label>
                <Select id="user_id" v-model="userId" class="mt-1.5">
                    <option value="">Todos los responsables</option>
                    <option v-for="user in users" :key="user.id" :value="user.id">
                        {{ user.name }}
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

            <div class="w-56">
                <label for="opportunity_id" class="block text-sm font-medium text-slate-700">Oportunidad</label>
                <Select id="opportunity_id" v-model="opportunityId" class="mt-1.5">
                    <option value="">Todas las oportunidades</option>
                    <option v-for="opportunity in opportunities" :key="opportunity.id" :value="opportunity.id">
                        {{ opportunity.name }}
                    </option>
                </Select>
            </div>

            <div>
                <label for="date_from" class="block text-sm font-medium text-slate-700">Desde</label>
                <TextInput id="date_from" v-model="dateFrom" class="mt-1.5" type="date" />
            </div>

            <div>
                <label for="date_to" class="block text-sm font-medium text-slate-700">Hasta</label>
                <TextInput id="date_to" v-model="dateTo" class="mt-1.5" type="date" />
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

                <tbody v-if="activities.data.length" class="divide-y divide-slate-100">
                    <tr
                        v-for="activity in activities.data"
                        :key="activity.id"
                        class="hover:bg-slate-50"
                    >
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">
                            {{ formatDateTime(activity.activity_date) }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-900">
                            {{ activity.type_label ?? activity.type }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">
                            <Link
                                v-if="activity.client"
                                :href="routes.clients.show(activity.client.id)"
                                class="hover:text-brand-700"
                            >
                                {{ activity.client.name }}
                            </Link>
                            <span v-else>—</span>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">
                            <Link
                                v-if="activity.opportunity"
                                :href="routes.opportunities.show(activity.opportunity.id)"
                                class="hover:text-brand-700"
                            >
                                {{ activity.opportunity.name }}
                            </Link>
                            <span v-else>—</span>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">
                            {{ activity.user?.name ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-3">
                            <Badge :tone="statusTone(activity.status)">
                                {{ activity.status_label ?? activity.status }}
                            </Badge>
                        </td>
                        <td class="max-w-64 px-5 py-3">
                            <p class="line-clamp-1 text-sm text-slate-600">
                                {{ activity.description }}
                            </p>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-right">
                            <div class="inline-flex items-center gap-2">
                                <Link :href="routes.activities.show(activity.id)">
                                    <Button type="button" variant="secondary" size="sm">Ver</Button>
                                </Link>
                                <Link v-if="can.update" :href="routes.activities.edit(activity.id)">
                                    <Button type="button" variant="secondary" size="sm">Editar</Button>
                                </Link>
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
                            No hay actividades que coincidan con los criterios indicados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-3">
            <p class="text-sm text-slate-500">Mostrando {{ from }}–{{ to }} de {{ total }}</p>

            <div v-if="activities.links.length > 3" class="flex items-center gap-1">
                <Link
                    v-for="link in activities.links"
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