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
    roles: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    is_active: true,
    email_verified: false,
    // El rol por defecto de config('roles.default_role') lo aplica el servidor
    // si falta el campo, así que el selector empieza vacío.
    roles: [],
}).transform((data) => {
    // StoreUserRequest rechaza un request con "roles" sin users.assign_roles,
    // así que la clave se quita si no se muestra el selector.
    if (!props.can.assignRoles) {
        delete data.roles;
    }

    return data;
});

const submit = () => form.post(routes.users.store());
</script>

<template>
    <Head title="Nuevo usuario" />

    <PageHeader title="Nuevo usuario" description="Crea una cuenta con acceso al sistema." />

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
                    label="Contraseña"
                    :error="form.errors.password"
                    hint="Mínimo 8 caracteres."
                >
                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        required
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
                        required
                        :invalid="Boolean(form.errors.password_confirmation)"
                    />
                </FormField>

                <div class="space-y-3 border-t border-slate-200 pt-5">
                    <label class="flex items-start gap-3">
                        <Checkbox v-model="form.is_active" class="mt-0.5" />
                        <span class="text-sm text-slate-700">
                            Cuenta activa
                            <span class="block text-slate-500">Si la desmarcas, el usuario no podrá iniciar sesión.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3">
                        <Checkbox v-model="form.email_verified" class="mt-0.5" />
                        <span class="text-sm text-slate-700">
                            Correo electrónico verificado
                            <span class="block text-slate-500">Marca la casilla si has confirmado la dirección por otra vía.</span>
                        </span>
                    </label>
                </div>
            </div>
        </Card>

        <Card v-if="can.assignRoles" title="Roles" class="mt-5">
            <p class="text-sm text-slate-500">
                Si no marcas ninguno, la cuenta recibe el rol predeterminado del sistema.
            </p>

            <RoleSelector v-model="form.roles" :roles="roles" />

            <p v-if="form.errors.roles" class="mt-3 text-sm text-red-600">
                {{ form.errors.roles }}
            </p>
        </Card>

        <div class="mt-5 flex items-center gap-3">
            <Button type="submit" :disabled="form.processing">
                {{ form.processing ? 'Creando…' : 'Crear usuario' }}
            </Button>

            <Link :href="routes.users.index()">
                <Button type="button" variant="secondary">Cancelar</Button>
            </Link>
        </div>
    </form>
</template>
