<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import PageHeader from '../../Components/PageHeader.vue';
import TextInput from '../../Components/ui/TextInput.vue';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    contacts: { type: Object, required: true },
    filters: { type: Object, required: true },
    can: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
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
    router.get(
        routes.contacts.index({ search: search.value }),
        {},
        { preserveState: true, replace: true },
    );
}

function resetFilters() {
    search.value = '';
}

const hasFilters = computed(() => Boolean(search.value));
const from = computed(() => props.contacts.meta?.from ?? 0);
const to = computed(() => props.contacts.meta?.to ?? 0);
const total = computed(() => props.contacts.meta?.total ?? 0);

const primaryForm = useForm({});

function setPrimary(contact) {
    primaryForm.patch(routes.contacts.primary(contact.id));
}
</script>

<template>
    <Head title="Contactos" />

    <PageHeader
        title="Contactos"
        description="Consulta de contactos. Para registrar o modificar los de un cliente, entra en su ficha."
    />

    <Card>
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-56 flex-1">
                <label for="search" class="block text-sm font-medium text-slate-700">Buscar</label>
                <TextInput
                    id="search"
                    v-model="search"
                    class="mt-1.5"
                    type="search"
                    placeholder="Nombre, correo o nombre del cliente"
                />
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
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Cliente</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Cargo</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Teléfono</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Correo</th>
                        <th scope="col" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Acciones</th>
                    </tr>
                </thead>

                <tbody v-if="contacts.data.length" class="divide-y divide-slate-100">
                    <tr v-for="contact in contacts.data" :key="contact.id" class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-5 py-3 text-sm font-medium text-slate-900">
                            {{ contact.display_name }}
                            <span v-if="contact.is_primary" class="ml-1 text-xs font-medium text-brand-700">
                                Principal
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">
                            <Link :href="routes.clients.show(contact.client.id)" class="hover:text-brand-700">
                                {{ contact.client.name }}
                            </Link>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">{{ contact.position ?? '—' }}</td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">{{ contact.phone ?? '—' }}</td>
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-600">{{ contact.email ?? '—' }}</td>
                        <td class="whitespace-nowrap px-5 py-3 text-right">
                            <div class="inline-flex items-center gap-2">
                                <Button
                                    v-if="can.setPrimary && !contact.is_primary"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    :disabled="primaryForm.processing"
                                    @click="setPrimary(contact)"
                                >
                                    Marcar principal
                                </Button>

                                <Link v-if="can.update" :href="routes.contacts.edit(contact.id)">
                                    <Button type="button" variant="secondary" size="sm">Editar</Button>
                                </Link>

                                <ConfirmDialog
                                    v-if="can.delete"
                                    title="Eliminar contacto"
                                    :message="`¿Seguro que desea eliminar a ${contact.display_name}?`"
                                    :url="routes.contacts.destroy(contact.id)"
                                >
                                    Eliminar
                                </ConfirmDialog>
                            </div>
                        </td>
                    </tr>
                </tbody>

                <tbody v-else>
                    <tr>
                        <td colspan="6" class="px-5 py-10 text-center text-sm text-slate-500">
                            No se han encontrado contactos con los criterios indicados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-3">
            <p class="text-sm text-slate-500">Mostrando {{ from }}–{{ to }} de {{ total }}</p>

            <div v-if="contacts.links.length > 3" class="flex items-center gap-1">
                <Link
                    v-for="link in contacts.links"
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
