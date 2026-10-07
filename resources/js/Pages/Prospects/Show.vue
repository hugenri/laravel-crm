<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Badge from '../../Components/ui/Badge.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import PageHeader from '../../Components/PageHeader.vue';
import Select from '../../Components/ui/Select.vue';
import { formatDate } from '../../lib/format';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    prospect: { type: Object, required: true },
    statuses: { type: Object, required: true },
    qualifiedStatuses: { type: Array, required: true },
    convertedStatus: { type: String, required: true },
    can: { type: Object, required: true },
});

/**
 * El estado cambia con su propia acción porque prospects.change_status es un
 * permiso distinto de prospects.update, igual que en oportunidades. El
 * desplegable ofrece los cuatro estados del embudo: "convertido" se alcanza con
 * la conversión, que además crea el cliente y el contacto.
 */
const statusForm = useForm({ status: '' });

function changeStatus() {
    if (!statusForm.status || statusForm.status === props.prospect.status) {
        return;
    }

    statusForm.patch(routes.prospects.status(props.prospect.id, statusForm.status));
    statusForm.reset('status');
}

const isConverted = props.prospect.status === props.convertedStatus;

/**
 * Los estados que el backend declara elegibles, menos el que ya tiene el
 * prospecto: el desplegable no ofrece un destino que no cambia nada.
 *
 * La lista llega recortada desde PHP, la misma que valida el request, para que
 * la pantalla y la regla nunca se separen.
 */
const availableStatuses = Object.fromEntries(
    props.qualifiedStatuses
        .filter((key) => key !== props.prospect.status)
        .map((key) => [key, props.statuses[key]])
        .filter(([, label]) => label !== undefined),
);

function statusTone(status) {
    if (status === 'calificado') {
        return 'green';
    }

    if (status === 'convertido') {
        return 'brand';
    }

    if (status === 'no_interesado') {
        return 'slate';
    }

    return 'amber';
}
</script>

<template>
    <Head :title="prospect.name" />

    <PageHeader
        :title="prospect.name"
        :description="prospect.company ?? ''"
    >
        <template #actions>
            <Link :href="routes.prospects.index()">
                <Button type="button" variant="secondary">Volver</Button>
            </Link>

            <Link v-if="can.update" :href="routes.prospects.edit(prospect.id)">
                <Button type="button" variant="secondary">Editar</Button>
            </Link>

            <!--
                La conversión es irreversible y crea registros en el módulo
                de clientes, así que va en su propia pantalla: hay que ver
                los datos antes de confirmarlos. Solo aparece mientras el
                prospecto no esté convertido.
            -->
            <Link
                v-if="can.convert && ! isConverted"
                :href="routes.prospects.convertCreate(prospect.id)"
            >
                <Button type="button">Convertir a cliente</Button>
            </Link>

            <ConfirmDialog
                v-if="can.delete && ! isConverted"
                title="Eliminar prospecto"
                message="¿Seguro que desea eliminar este prospecto? Esta acción no se puede deshacer."
                :url="routes.prospects.destroy(prospect.id)"
            >
                Eliminar
            </ConfirmDialog>
        </template>
    </PageHeader>

    <div class="grid gap-5 lg:grid-cols-3">
        <Card class="lg:col-span-1" title="Información">
            <dl class="space-y-4">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Empresa</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">
                        {{ prospect.company ?? 'Sin empresa registrada' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Teléfono</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">
                        {{ prospect.phone ?? '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Correo electrónico
                    </dt>
                    <dd class="mt-0.5 text-sm text-slate-900">
                        {{ prospect.email ?? '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fuente</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">
                        {{ prospect.source_label ?? prospect.source }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Responsable
                    </dt>
                    <dd class="mt-0.5 text-sm text-slate-900">
                        {{ prospect.user?.name ?? 'Sin responsable asignado' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Registro</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">
                        {{ formatDate(prospect.created_at) }}
                    </dd>
                </div>
            </dl>
        </Card>

        <Card class="lg:col-span-2" title="Seguimiento">
            <dl class="space-y-4">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</dt>
                    <dd class="mt-1">
                        <Badge :tone="statusTone(prospect.status)">
                            {{ prospect.status_label ?? prospect.status }}
                        </Badge>
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Fecha de seguimiento
                    </dt>
                    <dd class="mt-0.5 text-sm text-slate-900">
                        {{ formatDate(prospect.follow_up_date) }}
                    </dd>
                </div>

                <div v-if="prospect.notes">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Notas</dt>
                    <dd class="mt-0.5 whitespace-pre-line text-sm text-slate-900">
                        {{ prospect.notes }}
                    </dd>
                </div>
            </dl>

            <!--
                El cambio de estado vive aquí y no en la edición porque exige
                prospects.change_status. Sin esto, quien puede editar los
                datos podría también calificar o descartar el registro.
            -->
            <form
                v-if="can.changeStatus && ! isConverted && Object.keys(availableStatuses).length"
                class="mt-5 flex flex-wrap items-end gap-3 border-t border-slate-200 pt-4"
                @submit.prevent="changeStatus"
            >
                <div class="w-56">
                    <label for="status" class="block text-sm font-medium text-slate-700">
                        Cambiar estado
                    </label>
                    <Select id="status" v-model="statusForm.status" class="mt-1.5">
                        <option value="">Selecciona un estado</option>
                        <option v-for="(label, key) in availableStatuses" :key="key" :value="key">
                            {{ label }}
                        </option>
                    </Select>
                </div>

                <Button
                    type="submit"
                    variant="secondary"
                    :disabled="statusForm.processing || ! statusForm.status"
                >
                    {{ statusForm.processing ? 'Actualizando…' : 'Actualizar estado' }}
                </Button>
            </form>

            <p class="mt-4 text-xs text-slate-500">
                <template v-if="isConverted">
                    Este prospecto ya se convirtió: dejó de ser parte del embudo y su seguimiento
                    se agenda ahora sobre el cliente.
                </template>
                <template v-else>
                    El estado Convertido se alcanza con la conversión, no desde aquí, porque además
                    registra el cliente y su contacto.
                </template>
            </p>
        </Card>
    </div>

    <Card
        class="mt-5"
        :title="isConverted ? 'Cliente y contacto generados' : 'Conversión a cliente'"
        :description="isConverted
            ? 'Registros creados al convertir este prospecto.'
            : 'La conversión crea un cliente y su contacto principal.'"
    >
        <div v-if="isConverted" class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-lg border border-slate-200 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cliente</p>
                <p class="mt-1">
                    <Link
                        v-if="prospect.client"
                        :href="routes.clients.show(prospect.client.id)"
                        class="text-sm font-medium text-brand-700 hover:text-brand-800"
                    >
                        {{ prospect.client.name }}
                    </Link>
                    <span v-else class="text-sm text-slate-500">
                        El cliente generado fue eliminado.
                    </span>
                </p>
            </div>

            <div class="rounded-lg border border-slate-200 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contacto</p>
                <p class="mt-1 text-sm font-medium text-slate-900">
                    {{ prospect.contact?.display_name ?? 'El contacto generado fue eliminado.' }}
                </p>
                <p v-if="prospect.contact?.position" class="text-xs text-slate-500">
                    {{ prospect.contact.position }}
                </p>
            </div>
        </div>

        <div v-else class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-500">
                Al convertir se crea el cliente con su contacto principal y el prospecto queda
                registrado como convertido. No se puede deshacer.
            </p>

            <Link
                v-if="can.convert"
                :href="routes.prospects.convertCreate(prospect.id)"
            >
                <Button type="button" variant="secondary">Revisar conversión</Button>
            </Link>
        </div>
    </Card>
</template>
