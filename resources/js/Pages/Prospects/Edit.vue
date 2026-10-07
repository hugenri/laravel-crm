<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Badge from '../../Components/ui/Badge.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import PageHeader from '../../Components/PageHeader.vue';
import ProspectForm from '../../Components/ProspectForm.vue';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    prospect: { type: Object, required: true },
    sources: { type: Object, required: true },
    statuses: { type: Object, required: true },
    users: { type: Array, required: true },
});

/**
 * "status" no se envía: el backend lo cambia por su propia acción, que exige
 * prospects.change_status, y UpdateProspectRequest prohibe el campo. El estado
 * actual se muestra aparte, en la tarjeta de la derecha.
 */
const form = useForm({
    name: props.prospect.name,
    company: props.prospect.company ?? '',
    phone: props.prospect.phone ?? '',
    email: props.prospect.email ?? '',
    source: props.prospect.source,
    user_id: props.prospect.user_id ?? '',
    follow_up_date: props.prospect.follow_up_date ?? '',
    notes: props.prospect.notes,
});

const submit = () => form.patch(routes.prospects.update(props.prospect.id));
</script>

<template>
    <Head title="Editar prospecto" />

    <PageHeader
        title="Editar prospecto"
        :description="prospect.name"
    >
        <template #actions>
            <Link :href="routes.prospects.show(prospect.id)">
                <Button type="button" variant="secondary">Volver a la ficha</Button>
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
                    :show-status="false"
                    @clear-error="form.clearError($event)"
                />
            </Card>

            <div class="mt-5 flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Guardando…' : 'Guardar cambios' }}
                </Button>

                <Link :href="routes.prospects.show(prospect.id)">
                    <Button type="button" variant="secondary">Cancelar</Button>
                </Link>
            </div>
        </form>

        <Card class="lg:col-span-1" title="Estado actual">
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</dt>
                    <dd class="mt-1">
                        <Badge tone="slate">
                            {{ prospect.status_label ?? prospect.status }}
                        </Badge>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fuente</dt>
                    <dd class="mt-0.5 text-slate-900">
                        {{ prospect.source_label ?? prospect.source }}
                    </dd>
                </div>
            </dl>

            <p class="mt-4 border-t border-slate-200 pt-4 text-xs text-slate-500">
                El estado no se edita aquí. Cambiarlo, y convertir el prospecto en cliente, son
                acciones propias de la ficha con permisos propios.
            </p>
        </Card>
    </div>
</template>
