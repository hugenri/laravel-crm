<script setup>
import { computed } from 'vue';
import FormField from './ui/FormField.vue';
import Select from './ui/Select.vue';
import TextInput from './ui/TextInput.vue';

/**
 * Formulario de oportunidad, usado en crear y editar.
 * Al seleccionar un cliente, el selector de contacto solo muestra
 * contactos de ese cliente. El backend valida esta relación.
 */
const props = defineProps({
    form: { type: Object, required: true },
    clients: { type: Array, required: true },
    users: { type: Array, required: true },
    contacts: { type: Array, required: true },
    stages: { type: Object, required: true },
});

const emit = defineEmits(['clear-error']);

/**
 * Los ids de los select llegan como string; los records usan numbers.
 * Comparamos como números para filtrar correctamente.
 */
const selectedClientId = computed(() =>
    props.form.client_id === '' || props.form.client_id === null || props.form.client_id === undefined
        ? null
        : Number(props.form.client_id),
);

/**
 * Contactos del cliente seleccionado.
 * Al cambiar de cliente se limpia el contacto si ya no corresponde.
 */
const availableContacts = computed(() =>
    props.contacts.filter((contact) => contact.client_id === selectedClientId.value),
);

function onClientChanged() {
    emit('clear-error', 'client_id');

    const stillValid = availableContacts.value.some(
        (contact) => Number(contact.id) === Number(props.form.contact_id),
    );

    if (!stillValid) {
        props.form.contact_id = '';
    }
}

function clearError(field) {
    emit('clear-error', field);
}
</script>

<template>
    <div class="space-y-5">
        <FormField for="name" label="Nombre de la oportunidad" :error="form.errors.name">
            <TextInput
                id="name"
                :model-value="form.name"
                autocomplete="off"
                required
                autofocus
                placeholder="Renovación de servicio"
                :invalid="Boolean(form.errors.name)"
                @update:model-value="form.name = $event; clearError('name')"
            />
        </FormField>

        <div class="grid gap-5 sm:grid-cols-2">
            <FormField for="client_id" label="Cliente" :error="form.errors.client_id">
                <Select
                    id="client_id"
                    :model-value="form.client_id"
                    :invalid="Boolean(form.errors.client_id)"
                    @update:model-value="form.client_id = $event; onClientChanged()"
                >
                    <option value="">Selecciona un cliente</option>
                    <option v-for="client in clients" :key="client.id" :value="client.id">
                        {{ client.name }}
                    </option>
                </Select>
            </FormField>

            <FormField
                for="contact_id"
                label="Contacto"
                :error="form.errors.contact_id"
                hint="Opcional. Solo se ofrecen los contactos del cliente elegido."
            >
                <Select
                    id="contact_id"
                    :model-value="form.contact_id"
                    :disabled="!form.client_id"
                    :invalid="Boolean(form.errors.contact_id)"
                    @update:model-value="form.contact_id = $event; clearError('contact_id')"
                >
                    <option value="">Sin contacto asociado</option>
                    <option v-for="contact in availableContacts" :key="contact.id" :value="contact.id">
                        {{ contact.first_name }} {{ contact.last_name ?? '' }}
                    </option>
                </Select>
            </FormField>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <FormField for="user_id" label="Responsable" :error="form.errors.user_id">
                <Select
                    id="user_id"
                    :model-value="form.user_id"
                    :invalid="Boolean(form.errors.user_id)"
                    @update:model-value="form.user_id = $event; clearError('user_id')"
                >
                    <option value="">Selecciona un responsable</option>
                    <option v-for="user in users" :key="user.id" :value="user.id">
                        {{ user.name }}
                    </option>
                </Select>
            </FormField>

            <FormField
                for="amount"
                label="Monto estimado"
                :error="form.errors.amount"
                hint="Opcional. En pesos mexicanos."
            >
                <TextInput
                    id="amount"
                    :model-value="form.amount"
                    type="text"
                    inputmode="decimal"
                    placeholder="0.00"
                    :invalid="Boolean(form.errors.amount)"
                    @update:model-value="form.amount = $event; clearError('amount')"
                />
            </FormField>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <FormField
                for="stage"
                label="Etapa"
                :error="form.errors.stage"
                hint="El estado se calcula a partir de la etapa."
            >
                <Select
                    id="stage"
                    :model-value="form.stage"
                    :invalid="Boolean(form.errors.stage)"
                    @update:model-value="form.stage = $event; clearError('stage')"
                >
                    <option v-for="(label, key) in stages" :key="key" :value="key">
                        {{ label }}
                    </option>
                </Select>
            </FormField>

            <FormField
                for="expected_close_date"
                label="Fecha estimada de cierre"
                :error="form.errors.expected_close_date"
            >
                <TextInput
                    id="expected_close_date"
                    :model-value="form.expected_close_date"
                    type="date"
                    :invalid="Boolean(form.errors.expected_close_date)"
                    @update:model-value="form.expected_close_date = $event; clearError('expected_close_date')"
                />
            </FormField>
        </div>

        <FormField for="notes" label="Notas" :error="form.errors.notes">
            <textarea
                id="notes"
                :value="form.notes ?? ''"
                rows="3"
                class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                :class="form.errors.notes ? 'border-red-500' : ''"
                @input="form.notes = $event.target.value || null; clearError('notes')"
            />
        </FormField>
    </div>
</template>
