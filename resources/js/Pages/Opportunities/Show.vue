<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Badge from '../../Components/ui/Badge.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import PageHeader from '../../Components/PageHeader.vue';
import { formatCurrency, formatDate, formatDateTime } from '../../lib/format';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    opportunity: { type: Object, required: true },
    openStatus: { type: String, required: true },
    // Null sin permiso para ver actividades: la tarjeta no se dibuja en vez
    // de quedar vacía.
    activities: { type: Array, default: null },
    can: { type: Object, required: true },
});

const statusForm = useForm({});

const isOpen = computed(() => props.opportunity.status === props.openStatus);

function statusTone(status) {
    if (status === 'ganada') {
        return 'green';
    }

    if (status === 'perdida') {
        return 'red';
    }

    return 'slate';
}

function activityTone(status) {
    if (status === 'completada') {
        return 'green';
    }

    if (status === 'cancelada') {
        return 'red';
    }

    return 'amber';
}

function changeStatus(status) {
    statusForm.patch(routes.opportunities.status(props.opportunity.id, status));
}
</script>

<template>
    <Head :title="opportunity.name" />

    <PageHeader :title="opportunity.name" :description="opportunity.client?.name ?? ''">
        <template #actions>
            <Link :href="routes.opportunities.index()">
                <Button type="button" variant="secondary">Volver</Button>
            </Link>

            <Link v-if="can.update" :href="routes.opportunities.edit(opportunity.id)">
                <Button type="button" variant="secondary">Editar</Button>
            </Link>

            <!--
                Cerrar y reabrir viven aquí y no en la edición porque exigen
                opportunities.change_status. La etapa y el estado se actualizan
                juntos en el servidor, nunca uno sin el otro.
            -->
            <template v-if="can.changeStatus">
                <Button
                    v-if="isOpen"
                    type="button"
                    :disabled="statusForm.processing"
                    @click="changeStatus('ganada')"
                >
                    Marcar ganada
                </Button>

                <Button
                    v-if="isOpen"
                    type="button"
                    variant="secondary"
                    :disabled="statusForm.processing"
                    @click="changeStatus('perdida')"
                >
                    Marcar perdida
                </Button>

                <Button
                    v-else
                    type="button"
                    variant="secondary"
                    :disabled="statusForm.processing"
                    @click="changeStatus(openStatus)"
                >
                    Reabrir
                </Button>
            </template>

            <ConfirmDialog
                v-if="can.delete"
                title="Eliminar oportunidad"
                message="¿Seguro que desea eliminar esta oportunidad? El cliente, el contacto y el responsable no se modifican."
                :url="routes.opportunities.destroy(opportunity.id)"
            >
                Eliminar
            </ConfirmDialog>
        </template>
    </PageHeader>

    <div class="grid gap-5 lg:grid-cols-3">
        <Card class="lg:col-span-1" title="Información">
            <dl class="space-y-4">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cliente</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">
                        <Link
                            v-if="opportunity.client"
                            :href="routes.clients.show(opportunity.client.id)"
                            class="hover:text-brand-700"
                        >
                            {{ opportunity.client.name }}
                        </Link>
                        <span v-else>—</span>
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contacto</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">
                        {{ opportunity.contact?.display_name ?? 'Sin contacto asociado' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Responsable</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">
                        {{ opportunity.user?.name ?? 'Sin responsable asignado' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Monto estimado</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">
                        {{ formatCurrency(opportunity.amount) }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Registro</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">
                        {{ formatDate(opportunity.created_at) }}
                    </dd>
                </div>
            </dl>
        </Card>

        <Card class="lg:col-span-2" title="Seguimiento">
            <dl class="space-y-4">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Etapa</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ opportunity.stage_label ?? opportunity.stage }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</dt>
                    <dd class="mt-1">
                        <Badge :tone="statusTone(opportunity.status)">
                            {{ opportunity.status_label ?? opportunity.status }}
                        </Badge>
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Fecha estimada de cierre
                    </dt>
                    <dd class="mt-0.5 text-sm text-slate-900">
                        {{ formatDate(opportunity.expected_close_date) }}
                    </dd>
                </div>

                <div v-if="opportunity.notes">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Notas</dt>
                    <dd class="mt-0.5 whitespace-pre-line text-sm text-slate-900">
                        {{ opportunity.notes }}
                    </dd>
                </div>
            </dl>

            <p class="mt-5 border-t border-slate-200 pt-4 text-xs text-slate-500">
                La etapa indica el avance y el estado se calcula a partir de ella. Al reabrir una
                oportunidad, la etapa se conserva y solo el estado vuelve a Abierta.
            </p>
        </Card>
    </div>

    <Card
        v-if="activities !== null"
        class="mt-5"
        title="Actividades"
        description="Seguimiento registrado en esta oportunidad."
    >
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Actividades</h2>
                    <p class="mt-0.5 text-sm text-slate-500">
                        Histórico de lo realizado para esta oportunidad.
                    </p>
                </div>

                <Link
                    v-if="can.activities?.create"
                    :href="routes.activities.create({ opportunity_id: opportunity.id, client_id: opportunity.client_id })"
                >
                    <Button type="button">Registrar actividad</Button>
                </Link>
            </div>
        </template>

        <div v-if="activities.length" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Fecha</th>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo</th>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Responsable</th>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</th>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Descripción</th>
                        <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    <tr v-for="activity in activities" :key="activity.id" class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">
                            {{ formatDateTime(activity.activity_date) }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-900">
                            {{ activity.type_label ?? activity.type }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">
                            {{ activity.user?.name ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-3">
                            <Badge :tone="activityTone(activity.status)">
                                {{ activity.status_label ?? activity.status }}
                            </Badge>
                        </td>
                        <td class="max-w-64 px-3 py-3">
                            <p class="line-clamp-1 text-sm text-slate-600">
                                {{ activity.description }}
                            </p>
                        </td>
                        <td class="whitespace-nowrap px-3 py-3 text-right">
                            <Link :href="routes.activities.show(activity.id)">
                                <Button type="button" variant="secondary" size="sm">Ver</Button>
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-else class="py-6 text-center text-sm text-slate-500">
            Este registro todavía no tiene actividades asociadas.
        </p>
    </Card>
</template>
