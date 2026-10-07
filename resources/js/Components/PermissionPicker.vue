<script setup>
import { computed } from 'vue';
import Checkbox from './ui/Checkbox.vue';

/**
 * Catálogo de permisos agrupado por módulo, la misma forma que arma
 * RoleController desde config('roles.permissions').
 *
 * Lo usan el alta, la edición y la ficha de solo lectura: un permiso nuevo
 * en la configuración aparece en los tres sin tocar este componente.
 */
const props = defineProps({
    groups: { type: Array, required: true },
    modelValue: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const selected = computed(() => props.modelValue.map(Number));

const isChecked = (permissionId) => selected.value.includes(Number(permissionId));

function toggle(permissionId, checked) {
    const id = Number(permissionId);

    emit(
        'update:modelValue',
        checked ? [...selected.value, id] : selected.value.filter((current) => current !== id),
    );
}

const selectedInGroup = (group) => group.permissions.filter((p) => isChecked(p.id)).length;
</script>

<template>
    <div class="space-y-5">
        <fieldset
            v-for="group in groups"
            :key="group.group"
            class="rounded-lg border border-slate-200 p-4"
        >
            <legend class="px-1 text-sm font-semibold text-slate-900">
                {{ group.group }}
                <span class="font-normal text-slate-500">
                    ({{ selectedInGroup(group) }} de {{ group.permissions.length }})
                </span>
            </legend>

            <ul class="mt-3 space-y-3">
                <li
                    v-for="permission in group.permissions"
                    :key="permission.id"
                    class="flex items-start gap-3"
                >
                    <Checkbox
                        :model-value="isChecked(permission.id)"
                        :disabled="disabled"
                        class="mt-0.5"
                        @update:model-value="toggle(permission.id, $event)"
                    />
                    <span class="text-sm text-slate-700">
                        {{ permission.label }}
                        <span class="block font-mono text-xs text-slate-400">{{ permission.name }}</span>
                    </span>
                </li>
            </ul>
        </fieldset>
    </div>
</template>
