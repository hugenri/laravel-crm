<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    invalid: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);

const classes = computed(() => [
    'mt-1 block w-full rounded-lg border-0 px-3 py-2 text-sm text-slate-900 shadow-sm ring-1 ring-inset',
    'focus:ring-2 focus:ring-inset',
    props.invalid ? 'ring-red-400 focus:ring-red-500' : 'ring-slate-200 focus:ring-brand-600',
]);
</script>

<template>
    <select
        :value="modelValue"
        :class="classes"
        v-bind="$attrs"
        @change="$emit('update:modelValue', $event.target.value)"
    >
        <slot />
    </select>
</template>
