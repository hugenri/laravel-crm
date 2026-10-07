<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import ClientForm from '../../Components/ClientForm.vue';
import PageHeader from '../../Components/PageHeader.vue';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    client: { type: Object, required: true },
    types: { type: Object, required: true },
});

const form = useForm({
    type: props.client.type,
    name: props.client.name,
    rfc: props.client.rfc ?? '',
    phone: props.client.phone ?? '',
    email: props.client.email ?? '',
    address: props.client.address ?? '',
    notes: props.client.notes ?? null,
});

const submit = () => form.patch(routes.clients.update(props.client.id));
</script>

<template>
    <Head :title="`Editar ${client.name}`" />

    <PageHeader :title="client.name" description="Edita los datos del cliente.">
        <template #actions>
            <Link :href="routes.clients.show(client.id)">
                <Button type="button" variant="secondary">Volver a la ficha</Button>
            </Link>
        </template>
    </PageHeader>

    <form class="max-w-2xl" @submit.prevent="submit">
        <Card title="Datos del cliente">
            <ClientForm
                :form="form"
                :types="types"
                @clear-error="form.clearError($event)"
            />

            <p class="mt-5 border-t border-slate-200 pt-4 text-sm text-slate-500">
                El estado no se modifica aquí: se cambia con su propia acción en la ficha del
                cliente, porque requiere un permiso distinto al de edición.
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
</template>
