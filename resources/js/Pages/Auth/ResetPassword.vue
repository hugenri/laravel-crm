<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import Button from '../../Components/ui/Button.vue';
import FormField from '../../Components/ui/FormField.vue';
import TextInput from '../../Components/ui/TextInput.vue';
import { routes } from '../../lib/routes';

defineOptions({ layout: AuthLayout });

const props = defineProps({
    email: { type: String, default: '' },
    token: { type: String, required: true },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(routes.auth.resetPassword(props.token), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Establecer nueva contraseña" />

    <div>
        <h2 class="text-lg font-semibold text-slate-900">Establecer nueva contraseña</h2>
        <p class="mt-1 text-sm text-slate-500">
            Elige una contraseña nueva para tu cuenta.
        </p>
    </div>

    <form class="mt-6 space-y-5" @submit.prevent="submit">
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

        <FormField for="password" label="Nueva contraseña" :error="form.errors.password">
            <TextInput
                id="password"
                v-model="form.password"
                type="password"
                autocomplete="new-password"
                required
                autofocus
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
            {{ form.processing ? 'Guardando…' : 'Guardar contraseña' }}
        </Button>
    </form>
</template>
