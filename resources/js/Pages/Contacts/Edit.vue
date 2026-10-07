<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import ContactForm from '../../Components/ContactForm.vue';
import PageHeader from '../../Components/PageHeader.vue';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    contact: { type: Object, required: true },
    client: { type: Object, required: true },
    contacts: { type: Array, required: true },
    can: { type: Object, default: () => ({}) },
});

const form = useForm({
    first_name: props.contact.first_name,
    last_name: props.contact.last_name ?? '',
    position: props.contact.position ?? '',
    phone: props.contact.phone ?? '',
    email: props.contact.email ?? '',
    notes: props.contact.notes ?? null,
    is_primary: Boolean(props.contact.is_primary),
}).transform((data) => {
    // Sin contacts.set_primary no se pinta el checkbox y el campo no viaja:
    // UpdateContactRequest toma cualquier is_primary del payload como intento
    // de cambiar el contacto principal.
    if (!props.can.setPrimary) {
        delete data.is_primary;
    }

    return data;
});

const submit = () => form.patch(routes.contacts.update(props.contact.id));
</script>

<template>
    <Head title="Editar contacto" />

    <PageHeader
        title="Editar contacto"
        :description="`${contact.display_name} · ${client.name}`"
    >
        <template #actions>
            <Link :href="routes.clients.show(client.id)">
                <Button type="button" variant="secondary">Volver al cliente</Button>
            </Link>
        </template>
    </PageHeader>

    <div class="grid gap-5 lg:grid-cols-3">
        <form class="lg:col-span-2" @submit.prevent="submit">
            <Card title="Datos del contacto">
                <ContactForm
                    :form="form"
                    :can="can"
                    @clear-error="form.clearError($event)"
                />

                <p class="mt-5 border-t border-slate-200 pt-4 text-sm text-slate-500">
                    El contacto no se puede trasladar a otro cliente desde aquí: pertenece a
                    {{ client.name }}.
                </p>
            </Card>

            <div class="mt-5 flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Guardando…' : 'Guardar cambios' }}
                </Button>

                <Link :href="routes.clients.show(client.id)">
                    <Button type="button" variant="secondary">Cancelar</Button>
                </Link>
            </div>
        </form>

        <Card
            class="lg:col-span-1"
            :title="`Otros contactos (${contacts.length})`"
            description="Un mismo cliente no puede tener dos contactos principales."
        >
            <ul v-if="contacts.length" class="space-y-2">
                <li
                    v-for="other in contacts"
                    :key="other.id"
                    class="flex items-center justify-between gap-2 text-sm"
                >
                    <span class="truncate text-slate-700">{{ other.display_name }}</span>
                    <span v-if="other.is_primary" class="shrink-0 text-xs font-medium text-brand-700">
                        Principal
                    </span>
                </li>
            </ul>

            <p v-else class="py-4 text-center text-sm text-slate-500">
                Este es el único contacto del cliente.
            </p>
        </Card>
    </div>
</template>
