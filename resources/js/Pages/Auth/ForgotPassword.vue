<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import Button from '../../Components/ui/Button.vue';
import FormField from '../../Components/ui/FormField.vue';
import TextInput from '../../Components/ui/TextInput.vue';
import { routes } from '../../lib/routes';

defineOptions({ layout: AuthLayout });

const form = useForm({
    email: '',
});

function submit() {
    form.post(routes.auth.forgotPassword());
}
</script>

<template>
    <Head title="Recuperar contraseña" />

    <div>
        <h2 class="text-lg font-semibold text-slate-900">Recuperar contraseña</h2>
        <p class="mt-1 text-sm text-slate-500">
            Indica tu correo electrónico y te enviaremos un enlace para establecer una nueva contraseña.
        </p>
    </div>

    <div
        v-if="form.wasSuccessful"
        role="status"
        class="mt-6 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-inset ring-emerald-200"
    >
        Si existe una cuenta con ese correo, recibirás las instrucciones en breve.
    </div>

    <form v-else class="mt-6 space-y-5" @submit.prevent="submit">
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

        <Button type="submit" class="w-full" :disabled="form.processing">
            {{ form.processing ? 'Enviando…' : 'Enviar enlace' }}
        </Button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-600">
        <Link :href="routes.auth.login()" class="font-medium text-brand-700 hover:text-brand-800">
            Volver al inicio de sesión
        </Link>
    </p>
</template>
