<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Badge from '../../Components/ui/Badge.vue';
import Button from '../../Components/ui/Button.vue';
import Card from '../../Components/ui/Card.vue';
import FormField from '../../Components/ui/FormField.vue';
import PageHeader from '../../Components/PageHeader.vue';
import Select from '../../Components/ui/Select.vue';
import TextInput from '../../Components/ui/TextInput.vue';
import { routes } from '../../lib/routes';

defineOptions({ layout: AppLayout });

const props = defineProps({
    prospect: { type: Object, required: true },
    clientTypes: { type: Object, required: true },
    initialClientType: { type: String, required: true },
    prefill: { type: Object, required: true },
});

/**
 * Conversión en dos mitades: el cliente que entra a la cartera y su contacto
 * principal.
 *
 * Los campos vienen precargados con lo del prospecto, pero nada se guarda solo:
 * se guarda únicamente lo que se confirme aquí. El backend vuelve a validar el
 * RFC contra el tipo elegido y que no esté repetido.
 *
 * No se crean oportunidades ni actividades: el seguimiento se agenda después,
 * como actividad del cliente ya registrado.
 */
const form = useForm({
    client_name: props.prefill.client_name,
    client_type: props.initialClientType,
    rfc: props.prefill.rfc,
    address: props.prefill.address,
    client_notes: props.prefill.client_notes,
    first_name: props.prefill.first_name,
    last_name: props.prefill.last_name,
    position: props.prefill.position,
    phone: props.prefill.phone,
    email: props.prefill.email,
});

const submit = () => form.post(routes.prospects.convertStore(props.prospect.id));

function clearError(field) {
    form.clearError(field);
}
</script>

<template>
    <Head title="Convertir prospecto" />

    <PageHeader
        title="Convertir prospecto"
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
            <Card title="Cliente a registrar" description="Entrará a la cartera de clientes.">
                <div class="space-y-5">
                    <FormField for="client_name" label="Nombre o razón social" :error="form.errors.client_name">
                        <TextInput
                            id="client_name"
                            :model-value="form.client_name"
                            autocomplete="off"
                            required
                            autofocus
                            :invalid="Boolean(form.errors.client_name)"
                            @update:model-value="form.client_name = $event; clearError('client_name')"
                        />
                    </FormField>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <FormField for="client_type" label="Tipo de cliente" :error="form.errors.client_type">
                            <Select
                                id="client_type"
                                :model-value="form.client_type"
                                :invalid="Boolean(form.errors.client_type)"
                                @update:model-value="form.client_type = $event; clearError('client_type')"
                            >
                                <option v-for="(label, key) in clientTypes" :key="key" :value="key">
                                    {{ label }}
                                </option>
                            </Select>
                        </FormField>

                        <FormField
                            for="rfc"
                            label="RFC"
                            :error="form.errors.rfc"
                            hint="Opcional. Debe coincidir con el tipo de cliente elegido."
                        >
                            <TextInput
                                id="rfc"
                                :model-value="form.rfc"
                                autocomplete="off"
                                placeholder="GSO101010ABC"
                                :invalid="Boolean(form.errors.rfc)"
                                @update:model-value="form.rfc = $event; clearError('rfc')"
                            />
                        </FormField>
                    </div>

                    <FormField for="address" label="Dirección" :error="form.errors.address">
                        <TextInput
                            id="address"
                            :model-value="form.address"
                            autocomplete="off"
                            :invalid="Boolean(form.errors.address)"
                            @update:model-value="form.address = $event; clearError('address')"
                        />
                    </FormField>

                    <FormField for="client_notes" label="Notas del cliente" :error="form.errors.client_notes">
                        <textarea
                            id="client_notes"
                            :value="form.client_notes"
                            rows="2"
                            class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                            :class="form.errors.client_notes ? 'border-red-500' : ''"
                            @input="form.client_notes = $event.target.value; clearError('client_notes')"
                        />
                    </FormField>
                </div>
            </Card>

            <Card
                class="mt-5"
                title="Contacto principal"
                description="Quedará como contacto principal del cliente nuevo."
            >
                <div class="space-y-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <FormField for="first_name" label="Nombre" :error="form.errors.first_name">
                            <TextInput
                                id="first_name"
                                :model-value="form.first_name"
                                autocomplete="off"
                                required
                                :invalid="Boolean(form.errors.first_name)"
                                @update:model-value="form.first_name = $event; clearError('first_name')"
                            />
                        </FormField>

                        <FormField for="last_name" label="Apellido" :error="form.errors.last_name">
                            <TextInput
                                id="last_name"
                                :model-value="form.last_name"
                                autocomplete="off"
                                :invalid="Boolean(form.errors.last_name)"
                                @update:model-value="form.last_name = $event; clearError('last_name')"
                            />
                        </FormField>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <FormField
                            for="position"
                            label="Puesto"
                            :error="form.errors.position"
                            hint="Opcional."
                        >
                            <TextInput
                                id="position"
                                :model-value="form.position"
                                autocomplete="off"
                                :invalid="Boolean(form.errors.position)"
                                @update:model-value="form.position = $event; clearError('position')"
                            />
                        </FormField>

                        <FormField for="phone" label="Teléfono" :error="form.errors.phone">
                            <TextInput
                                id="phone"
                                :model-value="form.phone"
                                type="tel"
                                autocomplete="off"
                                :invalid="Boolean(form.errors.phone)"
                                @update:model-value="form.phone = $event; clearError('phone')"
                            />
                        </FormField>
                    </div>

                    <FormField for="email" label="Correo electrónico" :error="form.errors.email">
                        <TextInput
                            id="email"
                            :model-value="form.email"
                            type="email"
                            autocomplete="off"
                            :invalid="Boolean(form.errors.email)"
                            @update:model-value="form.email = $event; clearError('email')"
                        />
                    </FormField>
                </div>
            </Card>

            <div class="mt-5 flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Convirtiendo…' : 'Convertir a cliente' }}
                </Button>

                <Link :href="routes.prospects.show(prospect.id)">
                    <Button type="button" variant="secondary">Cancelar</Button>
                </Link>
            </div>
        </form>

        <div class="space-y-5 lg:col-span-1">
            <Card title="Prospecto de origen" description="Los datos que se tomaron de aquí.">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nombre</dt>
                        <dd class="mt-0.5 text-slate-900">{{ prospect.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Empresa</dt>
                        <dd class="mt-0.5 text-slate-900">
                            {{ prospect.company ?? 'Sin empresa registrada' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fuente</dt>
                        <dd class="mt-0.5 text-slate-900">
                            {{ prospect.source_label ?? prospect.source }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</dt>
                        <dd class="mt-1">
                            <Badge tone="slate">
                                {{ prospect.status_label ?? prospect.status }}
                            </Badge>
                        </dd>
                    </div>
                </dl>
            </Card>

            <Card title="Qué va a pasar">
                <ul class="space-y-2 text-sm text-slate-600">
                    <li>Se crea el cliente con los datos que confirmes.</li>
                    <li>Se crea el contacto y queda como principal de ese cliente.</li>
                    <li>El prospecto pasa a Convertido y se conservan sus datos.</li>
                    <li>El seguimiento programado se limpia: ahora se agenda sobre el cliente.</li>
                </ul>

                <p class="mt-4 border-t border-slate-200 pt-4 text-xs text-slate-500">
                    La conversión no se puede deshacer y no se repite: un prospecto convertido ya no
                    puede volver a convertirse.
                </p>
            </Card>
        </div>
    </div>
</template>
