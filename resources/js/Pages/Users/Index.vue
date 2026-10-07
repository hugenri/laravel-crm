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
    users: { type: Object, required: true },
    filters: { type: Object, required: true },
    can: { type: Object, required: true },
});

const search = ref(props.filters.search);
const status = ref(props.filters.status);
const firstRender = ref(true);

let debounce;

watch([search, status], () => {
    if (firstRender.value) {
        firstRender.value = false;

        return;
    }

    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 300);
});

function applyFilters() {
    router.get(
        routes.users.index({ search: search.value, status: status.value }),
        {},
        { preserveState: true, replace: true },
    );
}

function resetFilters() {
    search.value = '';
    status.value = '';
}

const hasFilters = computed(() => Boolean(search.value || status.value));
const from = computed(() => (props.users.meta?.from ?? 0));
const to = computed(() => (props.users.meta?.to ?? 0));
const total = computed(() => props.users.meta?.total ?? 0);

const statusAction = useForm({});

function toggleStatus(user) {
    statusAction.patch(routes.users.status(user.id, user.is_active ? 'deactivate' : 'activate'));
}
</script>

<template>
    <Head title="Usuarios" />

    <PageHeader title="Usuarios" description="Cuentas con acceso al sistema y su estado.">
        <template #actions>
            <Link v-if="can.create" :href="routes.users.create()">
                <Button type="button">Nuevo usuario</Button>
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
                    placeholder="Nombre o correo electrónico"
                />
            </div>

            <div class="w-44">
                <label for="status" class="block text-sm font-medium text-slate-700">Estado</label>
                <Select id="status" v-model="status" class="mt-1.5">
                    <option value="">Todos</option>
                    <option value="active">Activos</option>
                    <option value="inactive">Inactivos</option>
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
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nombre</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Correo electrónico</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Roles</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Registro</th>
                        <th scope="col" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Acciones</th>
                    </tr>
                </thead>

                <tbody v-if="users.data.length" class="divide-y divide-slate-100">
                    <tr v-for="user in users.data" :key="user.id" class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-5 py-3 text-sm font-medium text-slate-900">
                            <Link
                                :href="routes.users.show(user.id)"
                                class="hover:text-brand-700"
                            >
                                {{ user.name }}
                            </Link>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">{{ user.email }}</td>
                        <td class="px-5 py-3">
                            <ul v-if="user.roles.length" class="flex flex-wrap gap-1">
                                <li
                                    v-for="role in user.roles"
                                    :key="role.id"
                                    class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700"
                                >
                                    {{ role.name }}
                                </li>
                            </ul>
                            <span v-else class="text-sm text-slate-400">—</span>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3"><StatusBadge :is-active="user.is_active" /></td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">{{ formatDate(user.created_at) }}</td>
                        <td class="whitespace-nowrap px-5 py-3 text-right">
                            <div class="inline-flex items-center gap-2">
                                <Link v-if="can.update" :href="routes.users.edit(user.id)">
                                    <Button type="button" variant="secondary" size="sm">Editar</Button>
                                </Link>
                                <Button
                                    v-if="can.changeStatus"
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    :disabled="statusAction.processing"
                                    @click="toggleStatus(user)"
                                >
                                    {{ user.is_active ? 'Desactivar' : 'Activar' }}
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>

                <tbody v-else>
                    <tr>
                        <td colspan="6" class="px-5 py-10 text-center text-sm text-slate-500">
                            No se han encontrado usuarios con los criterios indicados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-3">
            <p class="text-sm text-slate-500">
                Mostrando {{ from }}–{{ to }} de {{ total }}
            </p>

            <div v-if="users.links.length > 3" class="flex items-center gap-1">
                <Link
                    v-for="link in users.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    class="rounded-lg px-3 py-1.5 text-sm"
                    :class="[
                        link.active
                            ? 'bg-brand-600 text-white'
                            : 'text-slate-600 hover:bg-slate-100',
                        !link.url ? 'pointer-events-none opacity-40' : '',
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>
    </div>
</template>
