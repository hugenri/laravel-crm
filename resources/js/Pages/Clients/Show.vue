<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Badge from '../../Components/ui/Badge.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/ui/StatusBadge.vue';
import { formatCurrency, formatDate, formatDateTime } from '../../lib/format';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    client: { type: Object, required: true },
    contacts: { type: Array, required: true },
    // Null sin permiso para ver oportunidades: la tarjeta no se dibuja en vez
    // de quedar vacía.
    opportunities: { type: Array, default: null },
    // Null sin permiso para ver cotizaciones, mismo motivo.
    quotes: { type: Array, default: null },
    // Null sin permiso para ver actividades, mismo motivo.
    activities: { type: Array, default: null },
    activeStatus: { type: String, required: true },
    can: { type: Object, required: true },
});

const activeStatus = computed(() => props.activeStatus);

const statusForm = useForm({});

function toggleStatus() {
    statusForm.patch(routes.clients.status(props.client.id), {
        status: props.client.status === activeStatus.value ? 'inactivo' : activeStatus.value,
    });
}

const primaryForm = useForm({});

function setPrimary(contact) {
    primaryForm.patch(routes.contacts.primary(contact.id));
}

const canSetPrimary = computed(() => props.can.contacts.setPrimary);

function activityTone(status) {
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
    <Head :title="client.name" />

    <PageHeader :title="client.name" :description="`${client.phone ?? 'Sin teléfono'} · ${client.email ?? 'Sin correo'}`">
        <template #actions>
            <Link v-if="can.update" :href="routes.clients.edit(client.id)">
                <Button type="button" variant="secondary">Editar</Button>
            </Link>

            <Button
                v-if="can.changeStatus"
                type="button"
                variant="secondary"
                :disabled="statusForm.processing"
                @click="toggleStatus"
            >
                {{ client.status === activeStatus ? 'Inactivar' : 'Activar' }}
            </Button>

            <ConfirmDialog
                v-if="can.delete"
                title="Eliminar cliente"
                message="¿Seguro que desea eliminar este cliente? Si tiene contactos asociados, el sistema lo impedirá y podrás inactivarlo en su lugar."
                :url="routes.clients.destroy(client.id)"
            >
                Eliminar
            </ConfirmDialog>
        </template>
    </PageHeader>

    <div class="grid gap-5 lg:grid-cols-3">
        <Card class="lg:col-span-1" title="Datos del cliente">
            <dl class="space-y-4">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">{{ client.type_label ?? client.type }}</dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">RFC</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">{{ client.rfc ?? '—' }}</dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</dt>
                    <dd class="mt-1">
                        <StatusBadge :is-active="client.status === activeStatus" />
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dirección</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">{{ client.address ?? '—' }}</dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Registro</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">{{ formatDate(client.created_at) }}</dd>
                </div>

                <div v-if="client.notes">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Notas</dt>
                    <dd class="mt-0.5 whitespace-pre-line text-sm text-slate-900">{{ client.notes }}</dd>
                </div>
            </dl>
        </Card>

        <Card class="lg:col-span-2" title="Contactos">
            <template #header>
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Contactos</h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            Solo puede haber un contacto principal por cliente.
                        </p>
                    </div>

                    <Link v-if="can.contacts.create" :href="routes.clients.contactCreate(client.id)">
                        <Button type="button">Añadir contacto</Button>
                    </Link>
                </div>
            </template>

            <div v-if="contacts.length" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nombre</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Cargo</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Teléfono</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Correo</th>
                            <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Acciones</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="contact in contacts" :key="contact.id" class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-3 py-3 text-sm font-medium text-slate-900">
                                {{ contact.display_name }}
                                <Badge v-if="contact.is_primary" class="ml-2">{{ 'Principal' }}</Badge>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">{{ contact.position ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">{{ contact.phone ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">{{ contact.email ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <Button
                                        v-if="canSetPrimary && !contact.is_primary"
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        :disabled="primaryForm.processing"
                                        @click="setPrimary(contact)"
                                    >
                                        Marcar principal
                                    </Button>

                                    <Link v-if="can.contacts.update" :href="routes.contacts.edit(contact.id)">
                                        <Button type="button" variant="secondary" size="sm">Editar</Button>
                                    </Link>

                                    <ConfirmDialog
                                        v-if="can.contacts.delete"
                                        title="Eliminar contacto"
                                        :message="`¿Seguro que desea eliminar a ${contact.display_name}?`"
                                        :url="routes.contacts.destroy(contact.id)"
                                    >
                                        Eliminar
                                    </ConfirmDialog>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-else class="py-6 text-center text-sm text-slate-500">
                Este cliente todavía no tiene contactos registrados.
            </p>
        </Card>
    </div>

    <Card
        v-if="opportunities"
        class="mt-5"
        title="Oportunidades"
        description="Historial comercial de este cliente."
    >
        <div v-if="opportunities.length" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Oportunidad</th>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Responsable</th>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Monto</th>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Etapa</th>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</th>
                        <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    <tr v-for="opportunity in opportunities" :key="opportunity.id" class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-3 py-3 text-sm font-medium text-slate-900">
                            <Link
                                :href="routes.opportunities.show(opportunity.id)"
                                class="hover:text-brand-700"
                            >
                                {{ opportunity.name }}
                            </Link>
                        </td>
                        <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">
                            {{ opportunity.user?.name ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">
                            {{ formatCurrency(opportunity.amount) }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">
                            {{ opportunity.stage_label ?? opportunity.stage }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">
                            {{ opportunity.status_label ?? opportunity.status }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-3 text-right">
                            <Link :href="routes.opportunities.show(opportunity.id)">
                                <Button type="button" variant="secondary" size="sm">Ver</Button>
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-else class="py-6 text-center text-sm text-slate-500">
            Este cliente todavía no tiene oportunidades registradas.
        </p>
    </Card>

    <Card
        v-if="quotes"
        class="mt-5"
        title="Cotizaciones"
        description="Propuestas enviadas a este cliente."
    >
        <div v-if="quotes.length" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Folio</th>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Responsable</th>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Emisión</th>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Válida hasta</th>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Total</th>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</th>
                        <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    <tr v-for="quote in quotes" :key="quote.id" class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-3 py-3 text-sm font-medium text-slate-900">
                            <Link :href="routes.quotes.show(quote.id)" class="hover:text-brand-700">
                                {{ quote.folio }}
                            </Link>
                        </td>
                        <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">
                            {{ quote.user?.name ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">
                            {{ formatDate(quote.issue_date) }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">
                            {{ formatDate(quote.valid_until) }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">
                            {{ formatCurrency(quote.total) }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">
                            {{ quote.display_status_label ?? quote.display_status }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-3 text-right">
                            <Link :href="routes.quotes.show(quote.id)">
                                <Button type="button" variant="secondary" size="sm">Ver</Button>
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-else class="py-6 text-center text-sm text-slate-500">
            Este cliente todavía no tiene cotizaciones registradas.
        </p>
    </Card>

    <Card v-if="activities !== null" class="mt-5" title="Actividades">
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Actividades</h2>
                    <p class="mt-0.5 text-sm text-slate-500">
                        Seguimiento registrado con este cliente.
                    </p>
                </div>

                <Link
                    v-if="can.activities?.create"
                    :href="routes.activities.create({ client_id: client.id })"
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
                        <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Oportunidad</th>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Responsable</th>
                        <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</th>
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
                            <Link
                                v-if="activity.opportunity"
                                :href="routes.opportunities.show(activity.opportunity.id)"
                                class="hover:text-brand-700"
                            >
                                {{ activity.opportunity.name }}
                            </Link>
                            <span v-else>—</span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-3 text-sm text-slate-600">
                            {{ activity.user?.name ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-3">
                            <Badge :tone="activityTone(activity.status)">
                                {{ activity.status_label ?? activity.status }}
                            </Badge>
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
            Este cliente todavía no tiene actividades registradas.
        </p>
    </Card>
</template>
