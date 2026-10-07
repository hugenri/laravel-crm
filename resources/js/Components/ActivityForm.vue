<script setup>
import { computed } from 'vue';
import FormField from './ui/FormField.vue';
import Select from './ui/Select.vue';
import TextInput from './ui/TextInput.vue';

/**
 * Formulario de actividad, usado en crear y editar.
 * Al seleccionar un cliente, el selector de oportunidad solo muestra
 * las oportunidades de ese cliente. El backend valida esta relación.
 */
const props = defineProps({
    form: { type: Object, required: true },
    clients: { type: Array, required: true },
    users: { type: Array, required: true },
    opportunities: { type: Array, required: true },
    types: { type: Object, required: true },
    statuses: { type: Object, required: true },
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
 * Oportunidades del cliente seleccionado.
 * Al cambiar de cliente, se limpia la oportunidad si ya no corresponde.
 */
const availableOpportunities = computed(() =>
    props.opportunities.filter((opportunity) => opportunity.client_id === selectedClientId.value),
);

function onClientChanged() {
    emit('clear-error', 'client_id');

    const stillValid = availableOpportunities.value.some(
        (opportunity) => Number(opportunity.id) === Number(props.form.opportunity_id),
    );

    if (!stillValid) {
        props.form.opportunity_id = '';
    }
}

function clearError(field) {
    emit('clear-error', field);
}
</script>

<template>
    <div class="space-y-5">
        <div class="grid gap-5 sm:grid-cols-2">
            <FormField for="type" label="Tipo de actividad" :error="form.errors.type">
                <Select
                    id="type"
                    :model-value="form.type"
                    :invalid="Boolean(form.errors.type)"
                    @update:model-value="form.type = $event; clearError('type')"
                >
                    <option value="">Selecciona un tipo</option>
                    <option v-for="(label, key) in types" :key="key" :value="key">
                        {{ label }}
                    </option>
                </Select>
            </FormField>

            <FormField for="status" label="Estado" :error="form.errors.status">
                <Select
                    id="status"
                    :model-value="form.status"
                    :invalid="Boolean(form.errors.status)"
                    @update:model-value="form.status = $event; clearError('status')"
                >
                    <option v-for="(label, key) in statuses" :key="key" :value="key">
                        {{ label }}
                    </option>
                </Select>
            </FormField>
        </div>

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
                for="opportunity_id"
                label="Oportunidad"
                :error="form.errors.opportunity_id"
                hint="Opcional. Solo se ofrecen las oportunidades del cliente elegido."
            >
                <Select
                    id="opportunity_id"
                    :model-value="form.opportunity_id"
                    :disabled="!form.client_id"
                    :invalid="Boolean(form.errors.opportunity_id)"
                    @update:model-value="form.opportunity_id = $event; clearError('opportunity_id')"
                >
                    <option value="">Sin oportunidad asociada</option>
                    <option v-for="opportunity in availableOpportunities" :key="opportunity.id" :value="opportunity.id">
                        {{ opportunity.name }}
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

            <FormField for="activity_date" label="Fecha de la actividad" :error="form.errors.activity_date">
                <TextInput
                    id="activity_date"
                    :model-value="form.activity_date"
                    type="datetime-local"
                    :invalid="Boolean(form.errors.activity_date)"
                    @update:model-value="form.activity_date = $event; clearError('activity_date')"
                />
            </FormField>
        </div>

        <FormField
            for="description"
            label="Descripción"
            :error="form.errors.description"
            hint="Describa qué se hizo en el seguimiento."
        >
            <textarea
                id="description"
                :value="form.description"
                rows="4"
                class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                :class="form.errors.description ? 'border-red-500' : ''"
                required
                @input="form.description = $event.target.value; clearError('description')"
            />
        </FormField>
    </div>
</template>