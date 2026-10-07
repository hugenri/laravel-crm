<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import PageHeader from '../../Components/PageHeader.vue';
import ProspectForm from '../../Components/ProspectForm.vue';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    sources: { type: Object, required: true },
    statuses: { type: Object, required: true },
    initialStatus: { type: String, required: true },
    users: { type: Array, required: true },
});

/**
 * "client_id" y "contact_id" no son parte del formulario: los escribe la
 * conversión y ProspectRequest prohíbe los dos campos, así que ni se envían ni
 * se pueden colar.
 */
const form = useForm({
    name: '',
    company: '',
    phone: '',
    email: '',
    source: '',
    user_id: '',
    status: props.initialStatus,
    follow_up_date: '',
    notes: null,
});

const submit = () => form.post(routes.prospects.store());
</script>

<template>
    <Head title="Nuevo prospecto" />

    <PageHeader
        title="Nuevo prospecto"
        description="Una persona o empresa que todavía no es cliente."
    >
        <template #actions>
            <Link :href="routes.prospects.index()">
                <Button type="button" variant="secondary">Volver</Button>
            </Link>
        </template>
    </PageHeader>

    <div class="grid gap-5 lg:grid-cols-3">
        <form class="lg:col-span-2" @submit.prevent="submit">
            <Card title="Datos del prospecto">
                <ProspectForm
                    :form="form"
                    :sources="sources"
                    :statuses="statuses"
                    :users="users"
                    @clear-error="form.clearError($event)"
                />
            </Card>

            <div class="mt-5 flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Guardando…' : 'Registrar prospecto' }}
                </Button>

                <Link :href="routes.prospects.index()">
                    <Button type="button" variant="secondary">Cancelar</Button>
                </Link>
            </div>
        </form>

        <Card
            class="lg:col-span-1"
            title="Estados del embudo"
            description="El estado dice en qué punto está el prospecto."
        >
            <ol class="space-y-2">
                <li
                    v-for="(label, key) in statuses"
                    :key="key"
                    class="flex items-center gap-2 text-sm"
                    :class="key === form.status ? 'font-medium text-brand-700' : 'text-slate-600'"
                >
                    {{ label }}
                </li>
            </ol>

            <p class="mt-4 border-t border-slate-200 pt-4 text-xs text-slate-500">
                Convertido no se elige aquí: se alcanza con la acción propia de la ficha, que además
                registra el cliente y su contacto.
            </p>
        </Card>
    </div>
</template>
