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
    quote: { type: Object, required: true },
    clients: { type: Array, required: true },
    users: { type: Array, required: true },
    contacts: { type: Array, required: true },
    opportunities: { type: Array, required: true },
});

/**
 * Solo viajan los campos que el usuario controla: el folio se queda en la
 * cotización y los importes se recalculan al guardar, así que lo que se ve al
 * abrir la edición es lo que el backend guardó.
 */
const form = useForm({
    client_id: props.quote.client_id ?? '',
    contact_id: props.quote.contact_id ?? '',
    opportunity_id: props.quote.opportunity_id ?? '',
    user_id: props.quote.user_id ?? '',
    issue_date: props.quote.issue_date ?? '',
    valid_until: props.quote.valid_until ?? '',
    tax_rate: props.quote.tax_rate ?? '',
    notes: props.quote.notes ?? null,
    items: props.quote.items.map((item) => ({
        description: item.description,
        quantity: item.quantity,
        unit_price: item.unit_price,
    })),
});

const submit = () => form.patch(routes.quotes.update(props.quote.id));
</script>

<template>
    <Head title="Editar cotización" />

    <PageHeader :title="`Cotización ${quote.folio}`" description="Modifica el detalle de la propuesta.">
        <template #actions>
            <Link :href="routes.quotes.show(quote.id)">
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
                {{ form.processing ? 'Guardando…' : 'Guardar cambios' }}
            </Button>

            <Link :href="routes.quotes.show(quote.id)">
                <Button type="button" variant="secondary">Cancelar</Button>
            </Link>
        </div>
    </form>
</template>
