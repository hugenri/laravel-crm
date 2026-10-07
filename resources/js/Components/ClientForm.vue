<script setup>
import FormField from './ui/FormField.vue';
import Select from './ui/Select.vue';
import TextInput from './ui/TextInput.vue';

/**
 * Formulario de cliente compartido entre crear y editar.
 * El estado no se cambia aquí; se usa la acción específica.
 */
defineProps({
    form: { type: Object, required: true },
    types: { type: Object, required: true },
    /** Para formulario de creación: muestra estado inicial. */
    statuses: { type: Object, default: null },
});

const emit = defineEmits(['clear-error']);

function clearError(field) {
    emit('clear-error', field);
}
</script>

<template>
    <div class="space-y-5">
        <div class="grid gap-5 sm:grid-cols-2">
            <FormField for="type" label="Tipo de cliente" :error="form.errors.type">
                <Select
                    id="type"
                    :model-value="form.type"
                    :invalid="Boolean(form.errors.type)"
                    @update:model-value="form.type = $event; clearError('type')"
                >
                    <option v-for="(label, key) in types" :key="key" :value="key">
                        {{ label }}
                    </option>
                </Select>
            </FormField>

            <FormField for="name" label="Nombre o razón social" :error="form.errors.name">
                <TextInput
                    id="name"
                    :model-value="form.name"
                    autocomplete="off"
                    required
                    autofocus
                    :invalid="Boolean(form.errors.name)"
                    @update:model-value="form.name = $event; clearError('name')"
                />
            </FormField>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <FormField
                for="rfc"
                label="RFC"
                :error="form.errors.rfc"
                hint="Opcional. 12 caracteres para empresa y 13 para persona física."
            >
                <TextInput
                    id="rfc"
                    :model-value="form.rfc"
                    autocomplete="off"
                    :invalid="Boolean(form.errors.rfc)"
                    @update:model-value="form.rfc = $event; clearError('rfc')"
                />
            </FormField>

            <FormField for="phone" label="Teléfono" :error="form.errors.phone">
                <TextInput
                    id="phone"
                    :model-value="form.phone"
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

        <FormField for="address" label="Dirección" :error="form.errors.address">
            <TextInput
                id="address"
                :model-value="form.address"
                autocomplete="off"
                :invalid="Boolean(form.errors.address)"
                @update:model-value="form.address = $event; clearError('address')"
            />
        </FormField>

        <FormField
            v-if="statuses"
            for="status"
            label="Estado"
            :error="form.errors.status"
            hint="Después puedes cambiarlo desde la ficha del cliente."
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
