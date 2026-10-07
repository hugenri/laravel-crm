<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    type: { type: String, default: 'text' },
    invalid: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);

const classes = computed(() => [
    'block w-full rounded-lg border-0 px-3 py-2 text-sm text-slate-900 shadow-sm ring-1 ring-inset',
    'placeholder:text-slate-400 focus:ring-2 focus:ring-inset',
    props.invalid ? 'ring-red-400 focus:ring-red-500' : 'ring-slate-200 focus:ring-brand-600',
]);
</script>

<template>
    <input
        :type="type"
        :value="modelValue"
        :class="classes"
        v-bind="$attrs"
        @input="$emit('update:modelValue', $event.target.value)"
    />
</template>
