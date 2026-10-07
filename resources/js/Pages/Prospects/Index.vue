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
import { formatDate } from '../../lib/format';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    prospects: { type: Object, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Object, required: true },
    sources: { type: Object, required: true },
    sortable: { type: Array, required: true },
    users: { type: Array, required: true },
    can: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const source = ref(props.filters.source ?? '');
const userId = ref(props.filters.user_id ?? '');
const sort = ref(props.filters.sort);
const direction = ref(props.filters.direction);

const firstRender = ref(true);
let debounce;

watch([search, status, source, userId], () => {
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
        routes.prospects.index({
            search: search.value,
            status: status.value,
            source: source.value,
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
    source.value = '';
    userId.value = '';
}

function sortIndicator(column) {
    if (sort.value !== column) {
        return '';
    }

    return direction.value === 'asc' ? '↑' : '↓';
}

const hasFilters = computed(() =>
    Boolean(search.value || status.value || source.value || userId.value),
);
const from = computed(() => props.prospects.meta?.from ?? 0);
const to = computed(() => props.prospects.meta?.to ?? 0);
const total = computed(() => props.prospects.meta?.total ?? 0);

/**
 * Tonos del Badge compartido. "Calificado" es el único estado del embudo que
 * significa avance y "convertido" ya salió del embudo.
 *
 * El filtro de arriba sí ofrece los cinco estados, incluido "convertido": el
 * catálogo llega completo para poder dejar de ver los ya convertidos.
 */
function statusTone(status) {
    if (status === 'calificado') {
        return 'green';
    }

    if (status === 'convertido') {
        return 'brand';
    }

    if (status === 'no_interesado') {
        return 'slate';
    }

    return 'amber';
}

const columns = [
    { key: 'name', label: 'Prospecto' },
    { key: 'company', label: 'Empresa' },
    { key: 'source', label: 'Fuente' },
    { key: 'user', label: 'Responsable', sortable: false },
    { key: 'status', label: 'Estado' },
    { key: 'follow_up_date', label: 'Seguimiento' },
];
</script>

<template>
    <Head title="Prospectos" />

    <PageHeader
        title="Prospectos"
        description="Personas y empresas que todavía no son clientes."
    >
        <template #actions>
            <Link v-if="can.create" :href="routes.prospects.create()">
                <Button type="button">Nuevo prospecto</Button>
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
                    placeholder="Nombre, empresa, teléfono o correo"
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
                <label for="source" class="block text-sm font-medium text-slate-700">Fuente</label>
                <Select id="source" v-model="source" class="mt-1.5">
                    <option value="">Todas las fuentes</option>
                    <option v-for="(label, key) in sources" :key="key" :value="key">
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

                <tbody v-if="prospects.data.length" class="divide-y divide-slate-100">
                    <tr
                        v-for="prospect in prospects.data"
                        :key="prospect.id"
                        class="hover:bg-slate-50"
                    >
                        <td class="whitespace-nowrap px-5 py-3 text-sm font-medium text-slate-900">
                            <Link
                                :href="routes.prospects.show(prospect.id)"
                                class="hover:text-brand-700"
                            >
                                {{ prospect.name }}
                            </Link>
                            <p v-if="prospect.email" class="text-xs text-slate-500">
                                {{ prospect.email }}
                            </p>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">
                            {{ prospect.company ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">
                            {{ prospect.source_label ?? prospect.source }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">
                            {{ prospect.user?.name ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-3">
                            <Badge :tone="statusTone(prospect.status)">
                                {{ prospect.status_label ?? prospect.status }}
                            </Badge>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">
                            {{ formatDate(prospect.follow_up_date) }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-right">
                            <div class="inline-flex items-center gap-2">
                                <Link :href="routes.prospects.show(prospect.id)">
                                    <Button type="button" variant="secondary" size="sm">Ver</Button>
                                </Link>
                                <Link v-if="can.update" :href="routes.prospects.edit(prospect.id)">
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
                            No hay prospectos que coincidan con los criterios indicados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-3">
            <p class="text-sm text-slate-500">Mostrando {{ from }}–{{ to }} de {{ total }}</p>

            <div v-if="prospects.links.length > 3" class="flex items-center gap-1">
                <Link
                    v-for="link in prospects.links"
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
