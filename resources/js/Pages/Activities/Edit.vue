<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ActivityForm from '../../Components/ActivityForm.vue';
import Badge from '../../Components/ui/Badge.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import PageHeader from '../../Components/PageHeader.vue';
import { formatDateTime } from '../../lib/format';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    activity: { type: Object, required: true },
    clients: { type: Array, required: true },
    users: { type: Array, required: true },
    opportunities: { type: Array, required: true },
    types: { type: Object, required: true },
    statuses: { type: Object, required: true },
});

/**
 * El input datetime-local espera "YYYY-MM-DDTHH:mm"; el modelo guarda el
 * momento completo con segundos y zona horaria, así que se recorta.
 */
const form = useForm({
    type: props.activity.type,
    client_id: props.activity.client_id,
    opportunity_id: props.activity.opportunity_id ?? '',
    user_id: props.activity.user_id ?? '',
    activity_date: props.activity.activity_date
        ? props.activity.activity_date.slice(0, 16).replace(' ', 'T')
        : '',
    status: props.activity.status,
    description: props.activity.description,
});

const submit = () => form.patch(routes.activities.update(props.activity.id));
</script>

<template>
    <Head title="Editar actividad" />

    <PageHeader
        title="Editar actividad"
        :description="activity.type_label ?? activity.type"
    >
        <template #actions>
            <Link :href="routes.activities.show(activity.id)">
                <Button type="button" variant="secondary">Volver a la ficha</Button>
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
                    {{ form.processing ? 'Guardando…' : 'Guardar cambios' }}
                </Button>

                <Link :href="routes.activities.show(activity.id)">
                    <Button type="button" variant="secondary">Cancelar</Button>
                </Link>
            </div>
        </form>

        <Card class="lg:col-span-1" title="Estado actual">
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo</dt>
                    <dd class="mt-0.5 text-slate-900">
                        {{ activity.type_label ?? activity.type }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</dt>
                    <dd class="mt-1">
                        <Badge :tone="activity.status === 'completada' ? 'green' : activity.status === 'cancelada' ? 'red' : 'amber'">
                            {{ activity.status_label ?? activity.status }}
                        </Badge>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Registrada</dt>
                    <dd class="mt-0.5 text-slate-900">
                        {{ formatDateTime(activity.created_at) }}
                    </dd>
                </div>
            </dl>

            <p class="mt-4 border-t border-slate-200 pt-4 text-xs text-slate-500">
                La fecha de la actividad es la del seguimiento, no la del registro.
            </p>
        </Card>
    </div>
</template>