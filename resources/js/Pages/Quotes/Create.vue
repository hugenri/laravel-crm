<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import PageHeader from '../../Components/PageHeader.vue';
import QuoteForm from '../../Components/QuoteForm.vue';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    clients: { type: Array, required: true },
    users: { type: Array, required: true },
    contacts: { type: Array, required: true },
    opportunities: { type: Array, required: true },
    taxRate: { type: String, required: true },
    issueDate: { type: String, required: true },
    validUntil: { type: String, required: true },
});

/**
 * "folio", "status", "subtotal", "tax_amount" y "total" no forman parte del
 * formulario: el folio y el estado los decide el servidor y los tres importes
 * salen de las partidas. El backend los prohibe si llegan, así que no se envían.
 */
const form = useForm({
    client_id: '',
    contact_id: '',
    opportunity_id: '',
    user_id: '',
    issue_date: props.issueDate,
    valid_until: props.validUntil,
    tax_rate: props.taxRate,
    notes: null,
    items: [{ description: '', quantity: '', unit_price: '' }],
});

const submit = () => form.post(routes.quotes.store());
</script>

<template>
    <Head title="Nueva cotización" />

    <PageHeader
        title="Nueva cotización"
        description="Una propuesta comercial con su detalle y sus importes."
    >
        <template #actions>
            <Link :href="routes.quotes.index()">
                <Button type="button" variant="secondary">Volver</Button>
            </Link>
        </template>
    </PageHeader>

    <form @submit.prevent="submit">
        <Card title="Datos de la cotización">
            <QuoteForm
                :form="form"
                :clients="clients"
                :users="users"
                :contacts="contacts"
                :opportunities="opportunities"
                @clear-error="form.clearError($event)"
            />
        </Card>

        <div class="mt-5 flex items-center gap-3">
            <Button type="submit" :disabled="form.processing">
                {{ form.processing ? 'Guardando…' : 'Registrar cotización' }}
            </Button>

            <Link :href="routes.quotes.index()">
                <Button type="button" variant="secondary">Cancelar</Button>
            </Link>

            <p class="text-sm text-slate-500">
                La cotización se registra en borrador. Enviarla al cliente es una acción aparte.
            </p>
        </div>
    </form>
</template>
