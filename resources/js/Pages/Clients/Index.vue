<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import PageHeader from '../../Components/PageHeader.vue';
import Select from '../../Components/ui/Select.vue';
import StatusBadge from '../../Components/ui/StatusBadge.vue';
import TextInput from '../../Components/ui/TextInput.vue';
import { formatDate } from '../../lib/format';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    clients: { type: Object, required: true },
    filters: { type: Object, required: true },
    types: { type: Object, required: true },
    statuses: { type: Object, required: true },
    activeStatus: { type: String, required: true },
    sortable: { type: Array, required: true },
    can: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const type = ref(props.filters.type ?? '');
const sort = ref(props.filters.sort);
const direction = ref(props.filters.direction);

const firstRender = ref(true);
let debounce;

watch([search, status, type], () => {
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
        routes.clients.index({
            search: search.value,
            status: status.value,
            type: type.value,
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
    type.value = '';
}

function sortIndicator(column) {
    if (sort.value !== column) {
        return '';
    }

    return direction.value === 'asc' ? '↑' : '↓';
}

const hasFilters = computed(() => Boolean(search.value || status.value || type.value));
const from = computed(() => props.clients.meta?.from ?? 0);
const to = computed(() => props.clients.meta?.to ?? 0);
const total = computed(() => props.clients.meta?.total ?? 0);

const activeStatus = props.activeStatus;

const statusForm = useForm({});

function toggleStatus(client) {
    statusForm.patch(routes.clients.status(client.id), {
        status: client.status === activeStatus ? 'inactivo' : activeStatus,
    });
}

const columns = [
    { key: 'name', label: 'Nombre o razón social' },
    { key: 'rfc', label: 'RFC' },
    { key: 'email', label: 'Correo electrónico' },
    { key: 'status', label: 'Estado' },
    { key: 'type', label: 'Tipo' },
    { key: 'created_at', label: 'Registro' },
];
</script>

<template>
    <Head title="Clientes" />

    <PageHeader title="Clientes" description="Empresas y personas a las que se les presta servicio.">
        <template #actions>
            <Link v-if="can.create" :href="routes.clients.create()">
                <Button type="button">Nuevo cliente</Button>
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
                    placeholder="Nombre, RFC o correo electrónico"
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

            <div class="w-44">
                <label for="type" class="block text-sm font-medium text-slate-700">Tipo</label>
                <Select id="type" v-model="type" class="mt-1.5">
                    <option value="">Todos los tipos</option>
                    <option v-for="(label, key) in types" :key="key" :value="key">
                        {{ label }}
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
                                v-if="sortable.includes(column.key)"
                                type="button"
                                class="inline-flex items-center gap-1 hover:text-slate-800"
                                @click="toggleSort(column.key)"
                            >
                                {{ column.label }}
                                <span aria-hidden="true">{{ sortIndicator(column.key) }}</span>
                            </button>
                            <span v-else>{{ column.label }}</span>
                        </th>
                        <th scope="col" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Acciones
                        </th>
                    </tr>
                </thead>

                <tbody v-if="clients.data.length" class="divide-y divide-slate-100">
                    <tr v-for="client in clients.data" :key="client.id" class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-5 py-3 text-sm font-medium text-slate-900">
                            <Link :href="routes.clients.show(client.id)" class="hover:text-brand-700">
                                {{ client.name }}
                            </Link>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">{{ client.rfc ?? '—' }}</td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">{{ client.email ?? '—' }}</td>
                        <td class="whitespace-nowrap px-5 py-3">
                            <StatusBadge :is-active="client.status === 'activo'" />
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">
                            {{ types[client.type] ?? client.type }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">
                            {{ formatDate(client.created_at) }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-right">
                            <div class="inline-flex items-center gap-2">
                                <Link :href="routes.clients.show(client.id)">
                                    <Button type="button" variant="secondary" size="sm">Ver</Button>
                                </Link>
                                <Link v-if="can.update" :href="routes.clients.edit(client.id)">
                                    <Button type="button" variant="secondary" size="sm">Editar</Button>
                                </Link>
                                <Button
                                    v-if="can.changeStatus"
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    :disabled="statusForm.processing"
                                    @click="toggleStatus(client)"
                                >
                                    {{ client.status === 'activo' ? 'Inactivar' : 'Activar' }}
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>

                <tbody v-else>
                    <tr>
                        <td :colspan="columns.length + 1" class="px-5 py-10 text-center text-sm text-slate-500">
                            No se han encontrado clientes con los criterios indicados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-3">
            <p class="text-sm text-slate-500">Mostrando {{ from }}–{{ to }} de {{ total }}</p>

            <div v-if="clients.links.length > 3" class="flex items-center gap-1">
                <Link
                    v-for="link in clients.links"
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
