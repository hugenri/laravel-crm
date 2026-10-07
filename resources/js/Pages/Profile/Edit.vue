<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import FormField from '../../Components/ui/FormField.vue';
import TextInput from '../../Components/ui/TextInput.vue';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const page = usePage();

const props = defineProps({
    mustVerifyEmail: { type: Boolean, default: false },
});

const user = computed(() => page.props.auth.user);

const profileForm = useForm({
    name: user.value.name,
    email: user.value.email,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const verifyForm = useForm({});

const updateProfile = () => profileForm.patch(routes.profile.update());
const updatePassword = () => passwordForm.put(routes.profile.password());
const resendVerification = () => verifyForm.post(routes.auth.verifyEmail());

const emailChanged = computed(
    () => profileForm.email !== '' && profileForm.email !== user.value.email,
);
</script>

<template>
    <Head title="Mi perfil" />

    <div class="mb-6">
        <h1 class="text-xl font-semibold tracking-tight text-slate-900">Mi perfil</h1>
        <p class="mt-1 text-sm text-slate-500">Actualiza tu información personal y tu contraseña.</p>
    </div>

    <div
        v-if="props.mustVerifyEmail && !user.email_verified_at"
        class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-inset ring-amber-200"
    >
        <p>Tu correo electrónico no está verificado.</p>
        <Button
            type="button"
            variant="secondary"
            size="sm"
            :disabled="verifyForm.processing"
            @click="resendVerification"
        >
            {{ verifyForm.processing ? 'Enviando…' : 'Reenviar enlace de verificación' }}
        </Button>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <Card title="Información personal" description="Tu nombre y correo electrónico de acceso.">
            <form class="space-y-5" @submit.prevent="updateProfile">
                <FormField for="name" label="Nombre" :error="profileForm.errors.name">
                    <TextInput
                        id="name"
                        v-model="profileForm.name"
                        autocomplete="name"
                        required
                        :invalid="Boolean(profileForm.errors.name)"
                    />
                </FormField>

                <FormField
                    for="email"
                    label="Correo electrónico"
                    :error="profileForm.errors.email"
                    :hint="emailChanged ? 'Al cambiar el correo se solicitará la verificación de nuevo.' : null"
                >
                    <TextInput
                        id="email"
                        v-model="profileForm.email"
                        type="email"
                        autocomplete="username"
                        required
                        :invalid="Boolean(profileForm.errors.email)"
                    />
                </FormField>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="profileForm.processing">
                        {{ profileForm.processing ? 'Guardando…' : 'Guardar cambios' }}
                    </Button>
                </div>
            </form>
        </Card>

        <Card title="Contraseña" description="Usa una contraseña que no utilices en otros servicios.">
            <form class="space-y-5" @submit.prevent="updatePassword">
                <FormField
                    for="current_password"
                    label="Contraseña actual"
                    :error="passwordForm.errors.current_password"
                >
                    <TextInput
                        id="current_password"
                        v-model="passwordForm.current_password"
                        type="password"
                        autocomplete="current-password"
                        required
                        :invalid="Boolean(passwordForm.errors.current_password)"
                    />
                </FormField>

                <FormField
                    for="new_password"
                    label="Nueva contraseña"
                    :error="passwordForm.errors.password"
                >
                    <TextInput
                        id="new_password"
                        v-model="passwordForm.password"
                        type="password"
                        autocomplete="new-password"
                        required
                        :invalid="Boolean(passwordForm.errors.password)"
                    />
                </FormField>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="passwordForm.processing">
                        {{ passwordForm.processing ? 'Guardando…' : 'Cambiar contraseña' }}
                    </Button>
                </div>
            </form>
        </Card>
    </div>
</template>
