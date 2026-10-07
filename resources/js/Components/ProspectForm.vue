<script setup>
import FormField from './ui/FormField.vue';
import Select from './ui/Select.vue';
import TextInput from './ui/TextInput.vue';

/**
 * Campos del prospecto, compartidos entre alta y edición.
 *
 * El estado no viaja en el formulario: lo cambia la acción propia que pide
 * prospects.change_status y UpdateProspectRequest rechaza el campo. Por eso
 * el alta lo ofrece y la edición no; decide la prop showStatus.
 *
 * La fuente sí se edita: es dato de contacto, no una decisión comercial.
 */
const props = defineProps({
    form: { type: Object, required: true },
    sources: { type: Object, required: true },
    statuses: { type: Object, required: true },
    users: { type: Array, required: true },
    showStatus: { type: Boolean, default: true },
});

const emit = defineEmits(['clear-error']);

function clearError(field) {
    emit('clear-error', field);
}
</script>

<template>
    <div class="space-y-5">
        <FormField for="name" label="Nombre" :error="form.errors.name">
            <TextInput
                id="name"
                :model-value="form.name"
                autocomplete="off"
                required
                autofocus
                placeholder="María López"
                :invalid="Boolean(form.errors.name)"
                @update:model-value="form.name = $event; clearError('name')"
            />
        </FormField>

        <div class="grid gap-5 sm:grid-cols-2">
            <FormField
                for="company"
                label="Empresa"
                :error="form.errors.company"
                hint="Opcional. Cuando el prospecto viene de una empresa."
            >
                <TextInput
                    id="company"
                    :model-value="form.company"
                    autocomplete="organization"
                    placeholder="Comercializadora del Norte"
                    :invalid="Boolean(form.errors.company)"
                    @update:model-value="form.company = $event; clearError('company')"
                />
            </FormField>

            <FormField for="source" label="Fuente" :error="form.errors.source">
                <Select
                    id="source"
                    :model-value="form.source"
                    :invalid="Boolean(form.errors.source)"
                    @update:model-value="form.source = $event; clearError('source')"
                >
                    <option value="">Selecciona una fuente</option>
                    <option v-for="(label, key) in sources" :key="key" :value="key">
                        {{ label }}
                    </option>
                </Select>
            </FormField>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <FormField for="phone" label="Teléfono" :error="form.errors.phone">
                <TextInput
                    id="phone"
                    :model-value="form.phone"
                    type="tel"
                    autocomplete="off"
                    placeholder="55 1234 5678"
                    :invalid="Boolean(form.errors.phone)"
                    @update:model-value="form.phone = $event; clearError('phone')"
                />
            </FormField>

            <FormField for="email" label="Correo electrónico" :error="form.errors.email">
                <TextInput
                    id="email"
                    :model-value="form.email"
                    type="email"
                    autocomplete="off"
                    placeholder="maria@ejemplo.com"
                    :invalid="Boolean(form.errors.email)"
                    @update:model-value="form.email = $event; clearError('email')"
                />
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
                v-if="showStatus"
                for="status"
                label="Estado"
                :error="form.errors.status"
                hint="Convertido se alcanza con la acción propia, no aquí."
            >
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

            <FormField
                for="follow_up_date"
                label="Fecha de seguimiento"
                :error="form.errors.follow_up_date"
                hint="Opcional. El día en que toca volver a contactarlo."
            >
                <TextInput
                    id="follow_up_date"
                    :model-value="form.follow_up_date"
                    type="date"
                    :invalid="Boolean(form.errors.follow_up_date)"
                    @update:model-value="form.follow_up_date = $event; clearError('follow_up_date')"
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
