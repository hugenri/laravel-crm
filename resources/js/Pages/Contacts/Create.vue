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
    client: { type: Object, required: true },
    contacts: { type: Array, required: true },
    can: { type: Object, default: () => ({}) },
});

const form = useForm({
    first_name: '',
    last_name: '',
    position: '',
    phone: '',
    email: '',
    notes: null,
    is_primary: false,
}).transform((data) => {
    // StoreContactRequest y UpdateContactRequest rechazan is_primary sin
    // contacts.set_primary, así que la clave se quita si no se pintó el
    // checkbox. Igual que el selector de roles del formulario de usuario.
    if (!props.can.setPrimary) {
        delete data.is_primary;
    }

    return data;
});

const submit = () => form.post(routes.clients.contactStore(props.client.id));
</script>

<template>
    <Head title="Nuevo contacto" />

    <PageHeader
        title="Nuevo contacto"
        :description="`Contacto de ${client.name}`"
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
            </Card>

            <div class="mt-5 flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Guardando…' : 'Registrar contacto' }}
                </Button>

                <Link :href="routes.clients.show(client.id)">
                    <Button type="button" variant="secondary">Cancelar</Button>
                </Link>
            </div>
        </form>

        <Card
            class="lg:col-span-1"
            :title="`Contactos actuales (${contacts.length})`"
            description="Márcalos como principal aquí si lo necesitas."
        >
            <ul v-if="contacts.length" class="space-y-2">
                <li
                    v-for="contact in contacts"
                    :key="contact.id"
                    class="flex items-center justify-between gap-2 text-sm"
                >
                    <span class="truncate text-slate-700">{{ contact.display_name }}</span>
                    <span v-if="contact.is_primary" class="shrink-0 text-xs font-medium text-brand-700">
                        Principal
                    </span>
                </li>
            </ul>

            <p v-else class="py-4 text-center text-sm text-slate-500">
                Este cliente todavía no tiene contactos.
            </p>
        </Card>
    </div>
</template>
