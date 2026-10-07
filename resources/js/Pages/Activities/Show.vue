<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Badge from '../../Components/ui/Badge.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import PageHeader from '../../Components/PageHeader.vue';
import { formatDateTime } from '../../lib/format';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    activity: { type: Object, required: true },
    can: { type: Object, required: true },
});

function statusTone(status) {
    if (status === 'completada') {
        return 'green';
    }

    if (status === 'cancelada') {
        return 'red';
    }

    return 'amber';
}
</script>

<template>
    <Head :title="`${activity.type_label ?? activity.type} · ${activity.client?.name ?? 'Actividad'}`" />

    <PageHeader
        :title="activity.type_label ?? activity.type"
        :description="activity.client?.name ?? ''"
    >
        <template #actions>
            <Link :href="routes.activities.index()">
                <Button type="button" variant="secondary">Volver</Button>
            </Link>

            <Link
                v-if="can.create && activity.client"
                :href="routes.activities.create({ client_id: activity.client_id })"
            >
                <Button type="button" variant="secondary">Nueva actividad de este cliente</Button>
            </Link>

            <Link v-if="can.update" :href="routes.activities.edit(activity.id)">
                <Button type="button" variant="secondary">Editar</Button>
            </Link>

            <ConfirmDialog
                v-if="can.delete"
                title="Eliminar actividad"
                message="¿Seguro que desea eliminar esta actividad? Esta acción no se puede deshacer."
                :url="routes.activities.destroy(activity.id)"
            >
                Eliminar
            </ConfirmDialog>
        </template>
    </PageHeader>

    <div class="grid gap-5 lg:grid-cols-3">
        <Card class="lg:col-span-1" title="Relación">
            <dl class="space-y-4">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cliente</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">
                        <Link
                            v-if="activity.client"
                            :href="routes.clients.show(activity.client.id)"
                            class="hover:text-brand-700"
                        >
                            {{ activity.client.name }}
                        </Link>
                        <span v-else>—</span>
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Oportunidad</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">
                        <Link
                            v-if="activity.opportunity"
                            :href="routes.opportunities.show(activity.opportunity.id)"
                            class="hover:text-brand-700"
                        >
                            {{ activity.opportunity.name }}
                        </Link>
                        <span v-else class="text-slate-500">Sin oportunidad asociada</span>
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Responsable</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">
                        {{ activity.user?.name ?? 'Sin responsable asignado' }}
                    </dd>
                </div>
            </dl>
        </Card>

        <Card class="lg:col-span-2" title="Seguimiento">
            <dl class="space-y-4">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ activity.type_label ?? activity.type }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</dt>
                    <dd class="mt-1">
                        <Badge :tone="statusTone(activity.status)">
                            {{ activity.status_label ?? activity.status }}
                        </Badge>
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Fecha de la actividad
                    </dt>
                    <dd class="mt-0.5 text-sm text-slate-900">
                        {{ formatDateTime(activity.activity_date) }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Descripción</dt>
                    <dd class="mt-0.5 whitespace-pre-line text-sm text-slate-900">
                        {{ activity.description }}
                    </dd>
                </div>
            </dl>
        </Card>
    </div>
</template>