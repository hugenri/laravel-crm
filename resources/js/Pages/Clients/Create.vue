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
    types: { type: Object, required: true },
    statuses: { type: Object, required: true },
    activeStatus: { type: String, required: true },
});

const form = useForm({
    type: Object.keys(props.types)[0],
    name: '',
    rfc: '',
    phone: '',
    email: '',
    address: '',
    status: props.activeStatus,
    notes: null,
});

const submit = () => form.post(routes.clients.store());
</script>

<template>
    <Head title="Nuevo cliente" />

    <PageHeader title="Nuevo cliente" description="Registra una empresa o una persona física." />

    <form class="max-w-2xl" @submit.prevent="submit">
        <Card title="Datos del cliente">
            <ClientForm
                :form="form"
                :types="types"
                :statuses="statuses"
                @clear-error="form.clearError($event)"
            />
        </Card>

        <div class="mt-5 flex items-center gap-3">
            <Button type="submit" :disabled="form.processing">
                {{ form.processing ? 'Guardando…' : 'Registrar cliente' }}
            </Button>

            <Link :href="routes.clients.index()">
                <Button type="button" variant="secondary">Cancelar</Button>
            </Link>
        </div>
    </form>
</template>
