<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Badge from '../../Components/ui/Badge.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import PageHeader from '../../Components/PageHeader.vue';
import Select from '../../Components/ui/Select.vue';
import TextInput from '../../Components/ui/TextInput.vue';
import { formatCurrency, formatDate } from '../../lib/format';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    opportunities: { type: Object, required: true },
    filters: { type: Object, required: true },
    stages: { type: Object, required: true },
    statuses: { type: Object, required: true },
    sortable: { type: Array, required: true },
    clients: { type: Array, required: true },
    users: { type: Array, required: true },
    can: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
const stage = ref(props.filters.stage ?? '');
const status = ref(props.filters.status ?? '');
const userId = ref(props.filters.user_id ?? '');
const clientId = ref(props.filters.client_id ?? '');
const sort = ref(props.filters.sort);
const direction = ref(props.filters.direction);

const firstRender = ref(true);
let debounce;

watch([search, stage, status, userId, clientId], () => {
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
        routes.opportunities.index({
            search: search.value,
            stage: stage.value,
            status: status.value,
            user_id: userId.value,
            client_id: clientId.value,
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
    stage.value = '';
    status.value = '';
    userId.value = '';
    clientId.value = '';
}

function sortIndicator(column) {
    if (sort.value !== column) {
        return '';
    }

    return direction.value === 'asc' ? '↑' : '↓';
}

const hasFilters = computed(() =>
    Boolean(search.value || stage.value || status.value || userId.value || clientId.value),
);
const from = computed(() => props.opportunities.meta?.from ?? 0);
const to = computed(() => props.opportunities.meta?.to ?? 0);
const total = computed(() => props.opportunities.meta?.total ?? 0);

/**
 * Tonos del Badge compartido: el listado no inventa sus propios colores.
 */
function statusTone(status) {
    if (status === 'ganada') {
        return 'green';
    }

    if (status === 'perdida') {
        return 'red';
    }

    return 'slate';
}

const columns = [
    { key: 'name', label: 'Oportunidad' },
    { key: 'client', label: 'Cliente', sortable: false },
    { key: 'contact', label: 'Contacto', sortable: false },
    { key: 'user', label: 'Responsable', sortable: false },
    { key: 'amount', label: 'Monto' },
    { key: 'stage', label: 'Etapa' },
    { key: 'status', label: 'Estado' },
    { key: 'expected_close_date', label: 'Cierre estimado' },
];
</script>

<template>
    <Head title="Oportunidades" />

    <PageHeader
        title="Oportunidades"
        description="Posibles ventas registradas por cliente."
    >
        <template #actions>
            <Link v-if="can.create" :href="routes.opportunities.create()">
                <Button type="button">Nueva oportunidad</Button>
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
                    placeholder="Nombre de la oportunidad o del cliente"
                />
            </div>

            <div class="w-44">
                <label for="stage" class="block text-sm font-medium text-slate-700">Etapa</label>
                <Select id="stage" v-model="stage" class="mt-1.5">
                    <option value="">Todas las etapas</option>
                    <option v-for="(label, key) in stages" :key="key" :value="key">
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

                <tbody v-if="opportunities.data.length" class="divide-y divide-slate-100">
                    <tr
                        v-for="opportunity in opportunities.data"
                        :key="opportunity.id"
                        class="hover:bg-slate-50"
                    >
                        <td class="whitespace-nowrap px-5 py-3 text-sm font-medium text-slate-900">
                            <Link
                                :href="routes.opportunities.show(opportunity.id)"
                                class="hover:text-brand-700"
                            >
                                {{ opportunity.name }}
                            </Link>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">
                            <Link
                                v-if="opportunity.client"
                                :href="routes.clients.show(opportunity.client.id)"
                                class="hover:text-brand-700"
                            >
                                {{ opportunity.client.name }}
                            </Link>
                            <span v-else>—</span>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">
                            {{ opportunity.contact?.display_name ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">
                            {{ opportunity.user?.name ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-900">
                            {{ formatCurrency(opportunity.amount) }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">
                            {{ opportunity.stage_label ?? opportunity.stage }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-3">
                            <Badge :tone="statusTone(opportunity.status)">
                                {{ opportunity.status_label ?? opportunity.status }}
                            </Badge>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">
                            {{ formatDate(opportunity.expected_close_date) }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-right">
                            <div class="inline-flex items-center gap-2">
                                <Link :href="routes.opportunities.show(opportunity.id)">
                                    <Button type="button" variant="secondary" size="sm">Ver</Button>
                                </Link>
                                <Link v-if="can.update" :href="routes.opportunities.edit(opportunity.id)">
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
                            No hay oportunidades que coincidan con los criterios indicados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-3">
            <p class="text-sm text-slate-500">Mostrando {{ from }}–{{ to }} de {{ total }}</p>

            <div v-if="opportunities.links.length > 3" class="flex items-center gap-1">
                <Link
                    v-for="link in opportunities.links"
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
