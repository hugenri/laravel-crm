<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import OpportunityForm from '../../Components/OpportunityForm.vue';
import PageHeader from '../../Components/PageHeader.vue';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    opportunity: { type: Object, required: true },
    clients: { type: Array, required: true },
    users: { type: Array, required: true },
    contacts: { type: Array, required: true },
    stages: { type: Object, required: true },
});

/**
 * "status" no forma parte del formulario: el backend lo deriva de la etapa y
 * el request rechaza el campo si llega, así que nunca se envía.
 */
const form = useForm({
    name: props.opportunity.name,
    client_id: props.opportunity.client_id,
    contact_id: props.opportunity.contact_id ?? '',
    user_id: props.opportunity.user_id ?? '',
    amount: props.opportunity.amount ?? '',
    stage: props.opportunity.stage,
    expected_close_date: props.opportunity.expected_close_date ?? '',
    notes: props.opportunity.notes,
});

const submit = () => form.patch(routes.opportunities.update(props.opportunity.id));
</script>

<template>
    <Head title="Editar oportunidad" />

    <PageHeader
        title="Editar oportunidad"
        :description="opportunity.name"
    >
        <template #actions>
            <Link :href="routes.opportunities.show(opportunity.id)">
                <Button type="button" variant="secondary">Volver a la ficha</Button>
            </Link>
        </template>
    </PageHeader>

    <div class="grid gap-5 lg:grid-cols-3">
        <form class="lg:col-span-2" @submit.prevent="submit">
            <Card title="Datos de la oportunidad">
                <OpportunityForm
                    :form="form"
                    :clients="clients"
                    :users="users"
                    :contacts="contacts"
                    :stages="stages"
                    @clear-error="form.clearError($event)"
                />
            </Card>

            <div class="mt-5 flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Guardando…' : 'Guardar cambios' }}
                </Button>

                <Link :href="routes.opportunities.show(opportunity.id)">
                    <Button type="button" variant="secondary">Cancelar</Button>
                </Link>
            </div>
        </form>

        <Card class="lg:col-span-1" title="Estado actual">
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Etapa</dt>
                    <dd class="mt-0.5 text-slate-900">
                        {{ opportunity.stage_label ?? opportunity.stage }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</dt>
                    <dd class="mt-0.5 text-slate-900">
                        {{ opportunity.status_label ?? opportunity.status }}
                    </dd>
                </div>
            </dl>

            <p class="mt-4 border-t border-slate-200 pt-4 text-xs text-slate-500">
                Al cambiar la etapa, el estado se recalcula en el servidor. Si la oportunidad estaba
                cerrada, volver a una etapa abierta la reabre.
            </p>
        </Card>
    </div>
</template>
