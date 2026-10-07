<script setup>
import Checkbox from './ui/Checkbox.vue';

/**
 * Selector de roles para los formularios de usuario.
 *
 * El checkbox trabaja con booleanos, así que la selección se alterna a mano
 * en vez de enlazar el array al input. Estar en un solo componente evita que
 * el alta y la edición se desincronicen.
 */
const props = defineProps({
    roles: { type: Array, required: true },
    modelValue: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const selected = () => props.modelValue.map(Number);

function toggle(roleId, checked) {
    const id = Number(roleId);

    emit(
        'update:modelValue',
        checked ? [...selected(), id] : selected().filter((current) => current !== id),
    );
}
</script>

<template>
    <ul class="mt-4 space-y-3">
        <li
            v-for="role in roles"
            :key="role.id"
            class="flex items-start gap-3"
        >
            <Checkbox
                :model-value="selected().includes(Number(role.id))"
                class="mt-0.5"
                @update:model-value="toggle(role.id, $event)"
            />
            <span class="text-sm text-slate-700">{{ role.name }}</span>
        </li>
    </ul>
</template>
