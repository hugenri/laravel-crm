<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import Checkbox from '../../Components/ui/Checkbox.vue';
import FormField from '../../Components/ui/FormField.vue';
import PageHeader from '../../Components/PageHeader.vue';
import RoleSelector from '../../Components/RoleSelector.vue';
import TextInput from '../../Components/ui/TextInput.vue';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    user: { type: Object, required: true },
    roles: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    email_verified: Boolean(props.user.email_verified_at),
    roles: [...(props.user.roles ?? [])],
}).transform((data) => {
    // Sin users.assign_roles el selector queda oculto, pero "oculto" no
    // protege: UpdateUserRequest rechaza cualquier roles sin ese permiso.
    // Quitar la clave aquí evita que quien solo edita datos básicos se atasque
    // por un campo que nunca se le ofreció.
    if (!props.can.assignRoles) {
        delete data.roles;
    }

    return data;
});

const submit = () => form.patch(routes.users.update(props.user.id));
</script>

<template>
    <Head :title="`Editar ${user.name}`" />

    <PageHeader
        :title="`Editar ${user.name}`"
        description="Los cambios se aplican de inmediato. El estado de la cuenta se modifica desde su ficha."
    >
        <template #actions>
            <Link :href="routes.users.show(user.id)">
                <Button type="button" variant="secondary">Ver ficha</Button>
            </Link>
        </template>
    </PageHeader>

    <form class="max-w-2xl" @submit.prevent="submit">
        <Card title="Datos de la cuenta">
            <div class="space-y-5">
                <FormField for="name" label="Nombre" :error="form.errors.name">
                    <TextInput
                        id="name"
                        v-model="form.name"
                        autocomplete="off"
                        required
                        autofocus
                        :invalid="Boolean(form.errors.name)"
                    />
                </FormField>

                <FormField for="email" label="Correo electrónico" :error="form.errors.email">
                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="off"
                        required
                        :invalid="Boolean(form.errors.email)"
                    />
                </FormField>

                <FormField
                    for="password"
                    label="Nueva contraseña"
                    :error="form.errors.password"
                    hint="Déjalo en blanco para mantener la contraseña actual."
                >
                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        :invalid="Boolean(form.errors.password)"
                    />
                </FormField>

                <FormField
                    for="password_confirmation"
                    label="Confirmar contraseña"
                    :error="form.errors.password_confirmation"
                >
                    <TextInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        :invalid="Boolean(form.errors.password_confirmation)"
                    />
                </FormField>

                <div class="border-t border-slate-200 pt-5">
                    <label class="flex items-start gap-3">
                        <Checkbox v-model="form.email_verified" class="mt-0.5" />
                        <span class="text-sm text-slate-700">
                            Correo electrónico verificado
                            <span class="block text-slate-500">Desmárcalo para que deba volver a verificar su dirección.</span>
                        </span>
                    </label>
                </div>
            </div>
        </Card>

        <Card v-if="can.assignRoles" title="Roles" class="mt-5">
            <p class="text-sm text-slate-500">
                Un usuario puede tener uno o varios roles. Sus permisos son la unión de los de todos ellos.
            </p>

            <RoleSelector v-model="form.roles" :roles="roles" />

            <p v-if="form.errors.roles" class="mt-3 text-sm text-red-600">
                {{ form.errors.roles }}
            </p>
        </Card>

        <div class="mt-5 flex items-center gap-3">
            <Button type="submit" :disabled="form.processing">
                {{ form.processing ? 'Guardando…' : 'Guardar cambios' }}
            </Button>

            <Link :href="routes.users.show(user.id)">
                <Button type="button" variant="secondary">Cancelar</Button>
            </Link>
        </div>
    </form>
</template>
