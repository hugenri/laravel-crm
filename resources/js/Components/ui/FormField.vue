<script setup>
import InputError from './InputError.vue';
import Label from './Label.vue';

defineProps({
    for: { type: String, required: true },
    label: { type: String, required: true },
    error: { type: String, default: null },
    hint: { type: String, default: null },
});
</script>

<template>
    <div>
        <Label :for="for">{{ label }}</Label>

        <div class="mt-1.5">
            <slot />
        </div>

        <InputError :message="error" />

        <p v-if="hint && !error" class="mt-1.5 text-sm text-slate-500">{{ hint }}</p>
    </div>
</template>
