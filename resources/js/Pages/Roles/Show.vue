<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import PageHeader from '../../Components/PageHeader.vue';
import PermissionPicker from '../../Components/PermissionPicker.vue';
import { formatDateTime } from '../../lib/format';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    role: { type: Object, required: true },
    permissions: { type: Array, required: true },
    permissionsTotal: { type: Number, required: true },
    can: { type: Object, required: true },
});

const selectedIds = computed(() => props.role.permissions.map(String));

const rows = computed(() => [
    { label: 'Usuarios con este rol', value: props.role.users_count },
    { label: 'Permisos concedidos', value: `${props.role.permissions.length} de ${props.permissionsTotal}` },
    { label: 'Creado', value: formatDateTime(props.role.created_at) },
    { label: 'Última actualización', value: formatDateTime(props.role.updated_at) },
]);

/**
 * Los botones que el servidor sí aceptaría. DeleteRole rechaza un rol del
 * sistema y un rol que alguien esté usando, así que esos casos no se ofrecen.
 */
const canDelete = computed(
    () => props.can.delete && !props.role.is_system && props.role.users_count === 0,
);

const deleteReason = computed(() => {
    if (!props.can.delete) {
        return null;
    }

    if (props.role.is_system) {
        return 'Es un rol del sistema y no se puede eliminar.';
    }

    if (props.role.users_count > 0) {
        return 'Hay usuarios con este rol, así que no se puede eliminar.';
    }

    return null;
});
</script>

<template>
    <Head :title="role.name" />

    <PageHeader :title="role.name" :description="role.is_system ? 'Rol del sistema' : 'Rol personalizado'">
        <template #actions>
            <Link :href="routes.roles.index()">
                <Button type="button" variant="ghost">Volver al listado</Button>
            </Link>

            <Link v-if="can.update" :href="routes.roles.edit(role.id)">
                <Button type="button">Editar</Button>
            </Link>
        </template>
    </PageHeader>

    <div class="grid gap-5 lg:grid-cols-3">
        <Card title="Resumen" class="lg:col-span-1">
            <dl class="space-y-4">
                <div
                    v-for="row in rows"
                    :key="row.label"
                    class="flex flex-wrap items-center justify-between gap-4"
                >
                    <dt class="text-sm text-slate-500">{{ row.label }}</dt>
                    <dd class="text-sm font-medium text-slate-900">{{ row.value }}</dd>
                </div>
            </dl>

            <p
                v-if="role.is_full_access"
                class="mt-5 rounded-lg bg-brand-50 p-3 text-sm text-brand-800"
            >
                Este rol conserva siempre todos los permisos del catálogo.
            </p>

            <template v-if="canDelete" #footer>
                <ConfirmDialog
                    title="Eliminar rol"
                    :message="`Se eliminará el rol ${role.name}. Esta acción no se puede deshacer.`"
                    :url="routes.roles.destroy(role.id)"
                >
                    Eliminar rol
                </ConfirmDialog>
            </template>

            <template v-else-if="deleteReason" #footer>
                <p class="text-xs text-slate-500">{{ deleteReason }}</p>
            </template>
        </Card>

        <Card title="Permisos" class="lg:col-span-2">
            <PermissionPicker :model-value="selectedIds" :groups="permissions" disabled />
        </Card>
    </div>
</template>
