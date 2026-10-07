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
    quote: { type: Object, required: true },
    statuses: { type: Object, required: true },
    availableStatuses: { type: Array, required: true },
    can: { type: Object, required: true },
});

const statusForm = useForm({});

/**
 * Los botones salen de "availableStatuses", que el backend calcula con la misma
 * tabla de transiciones que valida el cambio. La lista llega vacía sin
 * quotes.change_status y también cuando la cotización ya está en un estado
 * final: no hay nada que ofrecer que no vaya a ser rechazado.
 *
 * Cada botón lleva su verbo ("Marcar enviada", "Regresar a borrador") en vez
 * del nombre del estado, así que el nombre se busca aquí y la etiqueta de
 * config solo sirve de respaldo.
 */
const statusLabels = {
    borrador: 'Regresar a borrador',
    enviada: 'Marcar enviada',
    aceptada: 'Marcar aceptada',
    rechazada: 'Marcar rechazada',
    vencida: 'Marcar vencida',
};

const transitions = computed(() =>
    props.availableStatuses.map((status) => ({
        status,
        label: statusLabels[status] ?? props.statuses[status] ?? status,
    })),
);

/**
 * El estado que se muestra difiere del guardado cuando la vigencia ya venció:
 * la columna sigue diciendo borrador o enviada, pero la propuesta ya no está en
 * vigor. El backend lo decide con "display_status" para que la interfaz no
 * compare fechas.
 */
const isExpired = computed(() => props.quote.display_status === 'vencida' && props.quote.status !== 'vencida');

function changeStatus(status) {
    statusForm.patch(routes.quotes.status(props.quote.id, status));
}

function statusTone(status) {
    if (status === 'aceptada') {
        return 'green';
    }

    if (status === 'rechazada' || status === 'vencida') {
        return 'red';
    }

    if (status === 'enviada') {
        return 'brand';
    }

    return 'slate';
}

const primaryStatus = (status) => (status === 'enviada' ? 'primary' : 'secondary');
</script>

<template>
    <Head :title="`Cotización ${quote.folio}`" />

    <PageHeader
        :title="`Cotización ${quote.folio}`"
        :description="quote.client?.name ?? ''"
    >
        <template #actions>
            <Link :href="routes.quotes.index()">
                <Button type="button" variant="secondary">Volver</Button>
            </Link>

            <a
                v-if="can.pdf"
                :href="routes.quotes.pdf(quote.id)"
                class="inline-flex items-center justify-center rounded-lg bg-white px-3.5 py-2 text-sm font-medium text-slate-700 ring-1 ring-inset ring-slate-300 shadow-sm transition hover:bg-slate-50"
            >
                Descargar PDF
            </a>

            <Link v-if="can.update" :href="routes.quotes.edit(quote.id)">
                <Button type="button" variant="secondary">Editar</Button>
            </Link>

            <template v-if="can.changeStatus">
                <Button
                    v-for="transition in transitions"
                    :key="transition.status"
                    type="button"
                    :variant="primaryStatus(transition.status)"
                    :disabled="statusForm.processing"
                    @click="changeStatus(transition.status)"
                >
                    {{ transition.label }}
                </Button>
            </template>

            <ConfirmDialog
                v-if="can.delete"
                title="Eliminar cotización"
                message="¿Seguro que desea eliminar esta cotización? Se eliminarán también sus partidas. El cliente, el contacto y la oportunidad no se modifican."
                :url="routes.quotes.destroy(quote.id)"
            >
                Eliminar
            </ConfirmDialog>
        </template>
    </PageHeader>

    <p
        v-if="isExpired"
        class="mb-5 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-inset ring-amber-200"
    >
        La vigencia de esta cotización ya venció, por lo que se muestra como vencida aunque su
        estado guardado sea otro. Para volver a proponerla, regrésela a borrador y extienda la
        fecha de vigencia.
    </p>

    <p
        v-else-if="!can.update && quote.status !== 'borrador'"
        class="mb-5 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600 ring-1 ring-inset ring-slate-200"
    >
        Una cotización enviada ya no se edita: el cliente tiene el documento que se guardó. Para
        cambiar los importes, registre una cotización nueva.
    </p>

    <div class="grid gap-5 lg:grid-cols-3">
        <Card class="lg:col-span-1" title="Información">
            <dl class="space-y-4">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Folio</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">{{ quote.folio }}</dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</dt>
                    <dd class="mt-1">
                        <Badge :tone="statusTone(quote.display_status)">
                            {{ quote.display_status_label ?? quote.display_status }}
                        </Badge>
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cliente</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">
                        <Link
                            v-if="quote.client"
                            :href="routes.clients.show(quote.client.id)"
                            class="hover:text-brand-700"
                        >
                            {{ quote.client.name }}
                        </Link>
                        <span v-else>—</span>
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contacto</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">
                        {{ quote.contact?.display_name ?? 'Sin contacto asociado' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Oportunidad
                    </dt>
                    <dd class="mt-0.5 text-sm text-slate-900">
                        <Link
                            v-if="quote.opportunity"
                            :href="routes.opportunities.show(quote.opportunity.id)"
                            class="hover:text-brand-700"
                        >
                            {{ quote.opportunity.name }}
                        </Link>
                        <span v-else>Sin oportunidad asociada</span>
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Responsable
                    </dt>
                    <dd class="mt-0.5 text-sm text-slate-900">
                        {{ quote.user?.name ?? 'Sin responsable asignado' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Emisión</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">{{ formatDate(quote.issue_date) }}</dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Válida hasta
                    </dt>
                    <dd class="mt-0.5 text-sm text-slate-900">{{ formatDate(quote.valid_until) }}</dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Registro</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">{{ formatDateTime(quote.created_at) }}</dd>
                </div>
            </dl>
        </Card>

        <Card class="lg:col-span-2" title="Partidas">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th
                                scope="col"
                                class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                            >
                                #
                            </th>
                            <th
                                scope="col"
                                class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                            >
                                Descripción
                            </th>
                            <th
                                scope="col"
                                class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500"
                            >
                                Cantidad
                            </th>
                            <th
                                scope="col"
                                class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500"
                            >
                                Precio unitario
                            </th>
                            <th
                                scope="col"
                                class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500"
                            >
                                Importe
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in quote.items" :key="item.id">
                            <td class="px-3 py-3 text-sm text-slate-400">{{ item.position }}</td>
                            <td class="px-3 py-3 text-sm text-slate-900">{{ item.description }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-right text-sm text-slate-600">
                                {{ Number(item.quantity) }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-right text-sm text-slate-600">
                                {{ formatCurrency(item.unit_price) }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-right text-sm text-slate-900">
                                {{ formatCurrency(item.amount) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-5 flex justify-end border-t border-slate-200 pt-4">
                <dl class="w-full max-w-xs space-y-1.5">
                    <div class="flex items-center justify-between text-sm">
                        <dt class="text-slate-500">Subtotal</dt>
                        <dd class="font-medium text-slate-900">{{ formatCurrency(quote.subtotal) }}</dd>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <dt class="text-slate-500">IVA ({{ quote.tax_rate }}%)</dt>
                        <dd class="font-medium text-slate-900">{{ formatCurrency(quote.tax_amount) }}</dd>
                    </div>
                    <div class="flex items-center justify-between border-t border-slate-200 pt-2">
                        <dt class="text-sm font-semibold text-slate-900">Total</dt>
                        <dd class="text-base font-semibold text-slate-900">
                            {{ formatCurrency(quote.total) }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div v-if="quote.notes" class="mt-5 border-t border-slate-200 pt-4">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Notas</h3>
                <p class="mt-1 whitespace-pre-line text-sm text-slate-900">{{ quote.notes }}</p>
            </div>

            <p class="mt-5 text-xs text-slate-500">
                Los importes los calcula el sistema a partir de las partidas. El folio no se modifica
                y el estado se cambia con las acciones de arriba.
            </p>
        </Card>
    </div>
</template>
