<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ActivityForm from '../../Components/ActivityForm.vue';
import Badge from '../../Components/ui/Badge.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import PageHeader from '../../Components/PageHeader.vue';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    clients: { type: Array, required: true },
    users: { type: Array, required: true },
    opportunities: { type: Array, required: true },
    types: { type: Object, required: true },
    statuses: { type: Object, required: true },
    initialStatus: { type: String, required: true },
    // Precarga desde la ficha del cliente o la oportunidad: el backend ya validó
    // que la pareja sea válida.
    initialClientId: { type: Number, default: null },
    initialOpportunityId: { type: Number, default: null },
    // Precarga desde el calendario, ya en el formato "AAAA-MM-DDTHH:mm" que
    // espera el datetime-local. Llega a medianoche del día clicado; el usuario
    // puede mover la hora.
    initialActivityDate: { type: String, default: null },
});

const form = useForm({
    type: '',
    client_id: props.initialClientId ?? '',
    opportunity_id: props.initialOpportunityId ?? '',
    user_id: '',
    activity_date: props.initialActivityDate ?? '',
    status: props.initialStatus,
    description: '',
});

const submit = () => form.post(routes.activities.store());
</script>

<template>
    <Head title="Nueva actividad" />

    <PageHeader
        title="Nueva actividad"
        description="Registro del seguimiento realizado con un cliente."
    >
        <template #actions>
            <Link :href="routes.activities.index()">
                <Button type="button" variant="secondary">Volver</Button>
            </Link>
        </template>
    </PageHeader>

    <div class="grid gap-5 lg:grid-cols-3">
        <form class="lg:col-span-2" @submit.prevent="submit">
            <Card title="Seguimiento">
                <ActivityForm
                    :form="form"
                    :clients="clients"
                    :users="users"
                    :opportunities="opportunities"
                    :types="types"
                    :statuses="statuses"
                    @clear-error="form.clearError($event)"
                />
            </Card>

            <div class="mt-5 flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Guardando…' : 'Registrar actividad' }}
                </Button>

                <Link :href="routes.activities.index()">
                    <Button type="button" variant="secondary">Cancelar</Button>
                </Link>
            </div>
        </form>

        <Card class="lg:col-span-1" title="Estados">
            <dl class="space-y-2">
                <div
                    v-for="(label, key) in statuses"
                    :key="key"
                    class="flex items-center justify-between text-sm"
                >
                    <span
                        class="font-medium"
                        :class="key === form.status ? 'text-brand-700' : 'text-slate-600'"
                    >
                        {{ label }}
                    </span>
                    <Badge v-if="key === form.status" tone="brand">actual</Badge>
                </div>
            </dl>

            <p class="mt-4 border-t border-slate-200 pt-4 text-xs text-slate-500">
                El registro nace como Pendiente. Cancélala si el seguimiento quedó sin
                efecto; ajústala a Completada cuando termine.
            </p>
        </Card>
    </div>
</template>