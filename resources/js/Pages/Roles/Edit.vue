<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import FormField from '../../Components/ui/FormField.vue';
import PageHeader from '../../Components/PageHeader.vue';
import PermissionPicker from '../../Components/PermissionPicker.vue';
import TextInput from '../../Components/ui/TextInput.vue';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    role: { type: Object, required: true },
    permissions: { type: Array, required: true },
});

const form = useForm({
    name: props.role.name,
    permissions: [...props.role.permissions],
});

const submit = () => form.patch(routes.roles.update(props.role.id));

/**
 * El rol de acceso total siempre conserva el catálogo completo, así que sus
 * checkboxes aparecen concedidos y no se pueden desactivar.
 * App\Actions\Roles\SyncRolePermissions impone la misma regla en el servidor;
 * aquí solo se evita ofrecer un cambio que sería rechazado.
 */
const locked = computed(() => props.role.is_full_access);
</script>

<template>
    <Head :title="`Editar ${role.name}`" />

    <PageHeader
        :title="`Editar ${role.name}`"
        description="Los cambios en los permisos se aplican de inmediato a todos los usuarios con este rol."
    >
        <template #actions>
            <Link :href="routes.roles.show(role.id)">
                <Button type="button" variant="secondary">Ver ficha</Button>
            </Link>
        </template>
    </PageHeader>

    <form class="max-w-3xl" @submit.prevent="submit">
        <Card title="Datos del rol">
            <FormField for="name" label="Nombre" :error="form.errors.name">
                <TextInput
                    id="name"
                    v-model="form.name"
                    autocomplete="off"
                    required
                    :invalid="Boolean(form.errors.name)"
                />
            </FormField>
        </Card>

        <Card title="Permisos" class="mt-5">
            <p v-if="locked" class="mb-4 rounded-lg bg-brand-50 p-3 text-sm text-brand-800">
                Este rol mantiene todos los permisos del catálogo: es el que garantiza que siempre exista
                alguien capaz de administrar los roles. Para limitarlos, crea un rol nuevo.
            </p>

            <PermissionPicker v-model="form.permissions" :groups="permissions" :disabled="locked" />

            <p v-if="form.errors.permissions" class="mt-3 text-sm text-red-600">
                {{ form.errors.permissions }}
            </p>
        </Card>

        <div class="mt-5 flex items-center gap-3">
            <Button type="submit" :disabled="form.processing">
                {{ form.processing ? 'Guardando…' : 'Guardar cambios' }}
            </Button>

            <Link :href="routes.roles.show(role.id)">
                <Button type="button" variant="secondary">Cancelar</Button>
            </Link>
        </div>
    </form>
</template>
