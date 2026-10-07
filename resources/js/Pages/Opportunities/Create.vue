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
    clients: { type: Array, required: true },
    users: { type: Array, required: true },
    contacts: { type: Array, required: true },
    stages: { type: Object, required: true },
    initialStage: { type: String, required: true },
});

/**
 * "status" no forma parte del formulario: el backend lo deriva de la etapa y
 * el request rechaza el campo si llega, así que nunca se envía.
 */
const form = useForm({
    name: '',
    client_id: '',
    contact_id: '',
    user_id: '',
    amount: '',
    stage: props.initialStage,
    expected_close_date: '',
    notes: null,
});

const submit = () => form.post(routes.opportunities.store());
</script>

<template>
    <Head title="Nueva oportunidad" />

    <PageHeader
        title="Nueva oportunidad"
        description="Una posible venta asociada a un cliente."
    >
        <template #actions>
            <Link :href="routes.opportunities.index()">
                <Button type="button" variant="secondary">Volver</Button>
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
                    {{ form.processing ? 'Guardando…' : 'Registrar oportunidad' }}
                </Button>

                <Link :href="routes.opportunities.index()">
                    <Button type="button" variant="secondary">Cancelar</Button>
                </Link>
            </div>
        </form>

        <Card
            class="lg:col-span-1"
            title="Etapas"
            description="La etapa marca el avance comercial de la oportunidad."
        >
            <ol class="space-y-2">
                <li
                    v-for="(label, key) in stages"
                    :key="key"
                    class="flex items-center gap-2 text-sm"
                    :class="key === form.stage ? 'font-medium text-brand-700' : 'text-slate-600'"
                >
                    {{ label }}
                </li>
            </ol>

            <p class="mt-4 border-t border-slate-200 pt-4 text-xs text-slate-500">
                Ganada y Perdida no se eligen aquí: para cerrar una oportunidad se usa la acción
                propia de la ficha, que exige el permiso de cambio de estado.
            </p>
        </Card>
    </div>
</template>
