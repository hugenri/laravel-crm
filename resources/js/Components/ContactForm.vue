<script setup>
import Checkbox from './ui/Checkbox.vue';
import FormField from './ui/FormField.vue';
import TextInput from './ui/TextInput.vue';

/**
 * Formulario de contacto compartido entre crear y editar.
 * El cliente viene de la URL, no se puede cambiar desde este formulario.
 */
defineProps({
    form: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['clear-error']);

function clearError(field) {
    emit('clear-error', field);
}
</script>

<template>
    <div class="space-y-5">
        <div class="grid gap-5 sm:grid-cols-2">
            <FormField for="first_name" label="Nombre" :error="form.errors.first_name">
                <TextInput
                    id="first_name"
                    :model-value="form.first_name"
                    autocomplete="off"
                    required
                    autofocus
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

        <FormField for="position" label="Cargo" :error="form.errors.position">
            <TextInput
                id="position"
                :model-value="form.position"
                autocomplete="off"
                :invalid="Boolean(form.errors.position)"
                @update:model-value="form.position = $event; clearError('position')"
            />
        </FormField>

        <div class="grid gap-5 sm:grid-cols-2">
            <FormField for="phone" label="Teléfono" :error="form.errors.phone">
                <TextInput
                    id="phone"
                    :model-value="form.phone"
                    autocomplete="off"
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
                    :invalid="Boolean(form.errors.email)"
                    @update:model-value="form.email = $event; clearError('email')"
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

        <!--
            Solo para usuarios con permiso contacts.set_primary.
            El backend rechaza la petición si llega sin ese permiso.
        -->
        <div v-if="can.setPrimary" class="border-t border-slate-200 pt-5">
            <label class="flex items-start gap-3">
                <Checkbox v-model="form.is_primary" class="mt-0.5" />
                <span class="text-sm text-slate-700">
                    Marcar como contacto principal
                    <span class="block text-slate-500">
                        Solo puede haber un contacto principal por cliente; al marcarlo, el
                        anterior se desmarcará.
                    </span>
                </span>
            </label>
        </div>
    </div>
</template>
