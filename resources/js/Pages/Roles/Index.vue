<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import PageHeader from '../../Components/PageHeader.vue';
import TextInput from '../../Components/ui/TextInput.vue';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    roles: { type: Object, required: true },
    filters: { type: Object, required: true },
    permissionsTotal: { type: Number, required: true },
    can: { type: Object, required: true },
});

const search = ref(props.filters.search);
const firstRender = ref(true);

let debounce;

watch(search, () => {
    if (firstRender.value) {
        firstRender.value = false;

        return;
    }

    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 300);
});

function applyFilters() {
    router.get(routes.roles.index({ search: search.value }), {}, { preserveState: true, replace: true });
}

const from = computed(() => props.roles.meta?.from ?? 0);
const to = computed(() => props.roles.meta?.to ?? 0);
const total = computed(() => props.roles.meta?.total ?? 0);

const columns = computed(() => [
    { key: 'name', label: 'Rol' },
    { key: 'permissions', label: 'Permisos' },
    { key: 'users_count', label: 'Usuarios' },
    { key: 'actions', label: 'Acciones' },
]);
</script>

<template>
    <Head title="Roles y permisos" />

    <PageHeader
        title="Roles y permisos"
        description="Un rol agrupa permisos. Un usuario puede tener varios roles y sus permisos son la unión de todos ellos."
    >
        <template #actions>
            <Link v-if="can.create" :href="routes.roles.create()">
                <Button type="button">Nuevo rol</Button>
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
                    placeholder="Nombre del rol"
                />
            </div>
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
                            :class="column.key === 'actions' ? 'text-right' : ''"
                        >
                            {{ column.label }}
                        </th>
                    </tr>
                </thead>

                <tbody v-if="roles.data.length" class="divide-y divide-slate-100">
                    <tr v-for="role in roles.data" :key="role.id" class="hover:bg-slate-50">
                        <td class="px-5 py-3 text-sm font-medium text-slate-900">
                            <Link :href="routes.roles.show(role.id)" class="hover:text-brand-700">
                                {{ role.name }}
                            </Link>

                            <span
                                v-if="role.is_system"
                                class="ml-2 inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-200"
                            >
                                Sistema
                            </span>

                            <span
                                v-else-if="role.is_full_access"
                                class="ml-2 inline-flex items-center rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 ring-1 ring-inset ring-brand-200"
                            >
                                Acceso completo
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            <span class="text-sm text-slate-600">
                                {{ role.permissions.length }} de {{ permissionsTotal }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">{{ role.users_count }}</td>
                        <td class="whitespace-nowrap px-5 py-3 text-right">
                            <Link v-if="can.update" :href="routes.roles.edit(role.id)">
                                <Button type="button" variant="secondary" size="sm">Editar</Button>
                            </Link>
                        </td>
                    </tr>
                </tbody>

                <tbody v-else>
                    <tr>
                        <td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500">
                            No se han encontrado roles con los criterios indicados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-3">
            <p class="text-sm text-slate-500">Mostrando {{ from }}–{{ to }} de {{ total }}</p>

            <div v-if="roles.links.length > 3" class="flex items-center gap-1">
                <Link
                    v-for="link in roles.links"
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
