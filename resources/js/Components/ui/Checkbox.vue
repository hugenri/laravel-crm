<script setup>
defineProps({
    modelValue: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <input
        type="checkbox"
        :checked="modelValue"
        class="h-4 w-4 rounded border-slate-300 text-brand-600 shadow-sm focus:ring-2 focus:ring-brand-600"
        v-bind="$attrs"
        @change="$emit('update:modelValue', $event.target.checked)"
    />
</template>
