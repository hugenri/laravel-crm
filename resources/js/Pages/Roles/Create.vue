<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import FormField from '../../Components/ui/FormField.vue';
import PageHeader from '../../Components/PageHeader.vue';
import PermissionPicker from '../../Components/PermissionPicker.vue';
import TextInput from '../../Components/ui/TextInput.vue';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

defineProps({
    permissions: { type: Array, required: true },
});

const form = useForm({
    name: '',
    permissions: [],
});

const submit = () => form.post(routes.roles.store());
</script>

<template>
    <Head title="Nuevo rol" />

    <PageHeader
        title="Nuevo rol"
        description="Asigna a este rol los permisos que podrá ejercer dentro del CRM."
    >
        <template #actions>
            <Link :href="routes.roles.index()">
                <Button type="button" variant="secondary">Volver</Button>
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
                    autofocus
                    :invalid="Boolean(form.errors.name)"
                />
            </FormField>
        </Card>

        <Card title="Permisos" class="mt-5">
            <PermissionPicker v-model="form.permissions" :groups="permissions" />

            <p v-if="form.errors.permissions" class="mt-3 text-sm text-red-600">
                {{ form.errors.permissions }}
            </p>
        </Card>

        <div class="mt-5 flex items-center gap-3">
            <Button type="submit" :disabled="form.processing">
                {{ form.processing ? 'Guardando…' : 'Crear rol' }}
            </Button>

            <Link :href="routes.roles.index()">
                <Button type="button" variant="secondary">Cancelar</Button>
            </Link>
        </div>
    </form>
</template>
