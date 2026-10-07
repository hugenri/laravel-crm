<script setup>
import { computed } from 'vue';
import FormField from './ui/FormField.vue';
import Select from './ui/Select.vue';
import TextInput from './ui/TextInput.vue';
import QuoteItemsEditor from './QuoteItemsEditor.vue';

/**
 * Los campos de la cotizacion, compartidos por las pantallas de alta y de
 * edicion.
 *
 * Al elegir un cliente, el contacto y la oportunidad se limitan a los de ese
 * cliente, y cambiar de cliente limpia los que eran del anterior. Eso es
 * comodidad, no seguridad: si el contacto o la oportunidad no corresponden al
 * cliente, el backend lo rechaza con un error de campo.
 */
const props = defineProps({
    form: { type: Object, required: true },
    clients: { type: Array, required: true },
    users: { type: Array, required: true },
    contacts: { type: Array, required: true },
    opportunities: { type: Array, required: true },
    itemsMax: { type: Number, default: 50 },
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

const availableContacts = computed(() =>
    props.contacts.filter((contact) => contact.client_id === selectedClientId.value),
);

const availableOpportunities = computed(() =>
    props.opportunities.filter((opportunity) => opportunity.client_id === selectedClientId.value),
);

/**
 * Al cambiar de cliente se limpia el contacto y la oportunidad si ya no
 * corresponden: si no, se mandarían valores que el servidor rechaza.
 */
function onClientChanged() {
    emit('clear-error', 'client_id');

    if (!availableContacts.value.some((contact) => Number(contact.id) === Number(props.form.contact_id))) {
        props.form.contact_id = '';
    }

    if (!availableOpportunities.value.some((item) => Number(item.id) === Number(props.form.opportunity_id))) {
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
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
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
                    <option
                        v-for="opportunity in availableOpportunities"
                        :key="opportunity.id"
                        :value="opportunity.id"
                    >
                        {{ opportunity.name }}
                    </option>
                </Select>
            </FormField>
        </div>

        <div class="grid gap-5 sm:grid-cols-3">
            <FormField for="issue_date" label="Fecha de emisión" :error="form.errors.issue_date">
                <TextInput
                    id="issue_date"
                    :model-value="form.issue_date"
                    type="date"
                    required
                    :invalid="Boolean(form.errors.issue_date)"
                    @update:model-value="form.issue_date = $event; clearError('issue_date')"
                />
            </FormField>

            <FormField
                for="valid_until"
                label="Válida hasta"
                :error="form.errors.valid_until"
                hint="La propuesta caduca en esta fecha."
            >
                <TextInput
                    id="valid_until"
                    :model-value="form.valid_until"
                    type="date"
                    required
                    :invalid="Boolean(form.errors.valid_until)"
                    @update:model-value="form.valid_until = $event; clearError('valid_until')"
                />
            </FormField>

            <FormField
                for="tax_rate"
                label="IVA (%)"
                :error="form.errors.tax_rate"
                hint="El impuesto y el total los calcula el sistema."
            >
                <TextInput
                    id="tax_rate"
                    :model-value="form.tax_rate"
                    type="text"
                    inputmode="decimal"
                    placeholder="16.00"
                    :invalid="Boolean(form.errors.tax_rate)"
                    @update:model-value="form.tax_rate = $event; clearError('tax_rate')"
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

        <div class="border-t border-slate-200 pt-5">
            <h3 class="text-base font-semibold text-slate-900">Partidas</h3>

            <div class="mt-4">
                <QuoteItemsEditor
                    :form="form"
                    :tax-rate="String(form.tax_rate ?? '')"
                    :items-max="itemsMax"
                    @clear-error="emit('clear-error', $event)"
                />
            </div>
        </div>
    </div>
</template>
