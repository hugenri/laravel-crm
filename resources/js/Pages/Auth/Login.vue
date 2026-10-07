<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import Button from '../../Components/ui/Button.vue';
import Checkbox from '../../Components/ui/Checkbox.vue';
import FormField from '../../Components/ui/FormField.vue';
import TextInput from '../../Components/ui/TextInput.vue';
import { routes } from '../../lib/routes';

defineOptions({ layout: AuthLayout });

const page = usePage();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post(routes.auth.login(), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Iniciar sesión" />

    <div>
        <h2 class="text-lg font-semibold text-slate-900">Iniciar sesión</h2>
        <p class="mt-1 text-sm text-slate-500">Accede con tu cuenta de usuario.</p>
    </div>

    <form class="mt-6 space-y-5" @submit.prevent="submit">
        <FormField for="email" label="Correo electrónico" :error="form.errors.email">
            <TextInput
                id="email"
                v-model="form.email"
                type="email"
                autocomplete="username"
                required
                autofocus
                :invalid="Boolean(form.errors.email)"
            />
        </FormField>

        <FormField for="password" label="Contraseña" :error="form.errors.password">
            <TextInput
                id="password"
                v-model="form.password"
                type="password"
                autocomplete="current-password"
                required
                :invalid="Boolean(form.errors.password)"
            />
        </FormField>

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <Checkbox v-model="form.remember" />
                Recordarme
            </label>

            <Link
                :href="routes.auth.forgotPassword()"
                class="text-sm font-medium text-brand-700 hover:text-brand-800"
            >
                ¿Olvidaste tu contraseña?
            </Link>
        </div>

        <Button type="submit" class="w-full" :disabled="form.processing">
            {{ form.processing ? 'Comprobando…' : 'Iniciar sesión' }}
        </Button>
    </form>

    <p v-if="page.props.canRegister" class="mt-6 text-center text-sm text-slate-600">
        ¿No tienes cuenta?
        <Link :href="routes.auth.register()" class="font-medium text-brand-700 hover:text-brand-800">
            Regístrate
        </Link>
    </p>
</template>
