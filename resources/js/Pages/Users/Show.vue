<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/ui/StatusBadge.vue';
import { formatDateTime } from '../../lib/format';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    user: { type: Object, required: true },
    can: { type: Object, required: true },
});

const statusForm = useForm({});
const nextStatus = computed(() => (props.user.is_active ? 'deactivate' : 'activate'));

function changeStatus() {
    statusForm.patch(routes.users.status(props.user.id, nextStatus.value));
}

const rows = computed(() => [
    { label: 'Correo electrónico', value: props.user.email },
    { label: 'Fecha de registro', value: formatDateTime(props.user.created_at) },
    { label: 'Última actualización', value: formatDateTime(props.user.updated_at) },
    {
        label: 'Verificación del correo',
        value: props.user.email_verified_at
            ? formatDateTime(props.user.email_verified_at)
            : 'Sin verificar',
    },
]);
</script>

<template>
    <Head :title="user.name" />

    <PageHeader :title="user.name" :description="user.email">
        <template #actions>
            <Link :href="routes.users.index()">
                <Button type="button" variant="ghost">Volver al listado</Button>
            </Link>

            <Link v-if="can.update" :href="routes.users.edit(user.id)">
                <Button type="button">Editar</Button>
            </Link>
        </template>
    </PageHeader>

    <div class="grid gap-5 lg:grid-cols-3">
        <Card title="Estado" class="lg:col-span-1">
            <div class="flex items-center justify-between gap-4">
                <StatusBadge :is-active="user.is_active" />

                <Button
                    v-if="can.changeStatus"
                    type="button"
                    variant="secondary"
                    size="sm"
                    :disabled="statusForm.processing"
                    @click="changeStatus"
                >
                    {{ user.is_active ? 'Desactivar' : 'Activar' }}
                </Button>
            </div>
        </Card>

        <Card title="Ficha de la cuenta" class="lg:col-span-2">
            <dl class="space-y-4">
                <div v-for="row in rows" :key="row.label" class="flex flex-wrap items-center justify-between gap-4">
                    <dt class="text-sm text-slate-500">{{ row.label }}</dt>
                    <dd class="text-sm font-medium text-slate-900">{{ row.value }}</dd>
                </div>
            </dl>

            <div class="mt-5 border-t border-slate-200 pt-5">
                <dt class="text-sm text-slate-500">Roles</dt>

                <ul v-if="user.roles.length" class="mt-2 flex flex-wrap gap-2">
                    <li
                        v-for="role in user.roles"
                        :key="role.id"
                        class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700"
                    >
                        <Link
                            v-if="can.viewRoles"
                            :href="routes.roles.show(role.id)"
                            class="hover:text-brand-700"
                        >
                            {{ role.name }}
                        </Link>
                        <span v-else>{{ role.name }}</span>
                    </li>
                </ul>

                <p v-else class="mt-1 text-sm text-slate-500">
                    Sin roles asignados, por lo que no dispone de permisos administrativos.
                </p>
            </div>

            <template v-if="can.delete" #footer>
                <ConfirmDialog
                    title="Eliminar usuario"
                    :message="`Se eliminará la cuenta de ${user.name}. Esta acción no se puede deshacer.`"
                    :url="routes.users.destroy(user.id)"
                >
                    Eliminar
                </ConfirmDialog>
            </template>
        </Card>
    </div>
</template>
