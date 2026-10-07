<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import Button from '../../Components/ui/Button.vue';
import { routes } from '../../lib/routes';

defineOptions({ layout: AuthLayout });

const props = defineProps({
    status: { type: String, default: null },
});

const form = useForm({});

const sent = computed(() => form.wasSuccessful || props.status === 'verification-link-sent');

function submit() {
    form.post(routes.auth.verifyEmail());
}
</script>

<template>
    <Head title="Verifica tu correo electrónico" />

    <div>
        <h2 class="text-lg font-semibold text-slate-900">Verifica tu correo electrónico</h2>
        <p class="mt-1 text-sm text-slate-500">
            Te hemos enviado un enlace de verificación. Revisa tu bandeja de entrada para completar el registro.
        </p>
    </div>

    <div
        v-if="sent"
        role="status"
        class="mt-6 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-inset ring-emerald-200"
    >
        Si no has recibido el correo, revisa la carpeta de spam o solicita un nuevo enlace.
    </div>

    <div class="mt-6 flex items-center justify-between">
        <Button type="button" variant="secondary" :disabled="form.processing" @click="submit">
            {{ form.processing ? 'Reenviando…' : 'Reenviar enlace' }}
        </Button>

        <Link
            :href="routes.auth.logout()"
            method="post"
            as="button"
            class="text-sm font-medium text-slate-600 hover:text-slate-900"
        >
            Cerrar sesión
        </Link>
    </div>
</template>
