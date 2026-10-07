<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import Button from '../../Components/ui/Button.vue';
import FormField from '../../Components/ui/FormField.vue';
import TextInput from '../../Components/ui/TextInput.vue';
import { routes } from '../../lib/routes';

defineOptions({ layout: AuthLayout });

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(routes.auth.register(), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Crear cuenta" />

    <div>
        <h2 class="text-lg font-semibold text-slate-900">Crear cuenta</h2>
        <p class="mt-1 text-sm text-slate-500">Completa los datos para registrarte en el CRM.</p>
    </div>

    <form class="mt-6 space-y-5" @submit.prevent="submit">
        <FormField for="name" label="Nombre" :error="form.errors.name">
            <TextInput
                id="name"
                v-model="form.name"
                autocomplete="name"
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
                autocomplete="username"
                required
                :invalid="Boolean(form.errors.email)"
            />
        </FormField>

        <FormField for="password" label="Contraseña" :error="form.errors.password">
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

        <Button type="submit" class="w-full" :disabled="form.processing">
            {{ form.processing ? 'Creando cuenta…' : 'Crear cuenta' }}
        </Button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-600">
        ¿Ya tienes cuenta?
        <Link :href="routes.auth.login()" class="font-medium text-brand-700 hover:text-brand-800">
            Inicia sesión
        </Link>
    </p>
</template>
