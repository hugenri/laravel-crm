<script setup>
defineProps({
    for: { type: String, required: true },
});
</script>

<template>
    <label :for="for" class="block text-sm font-medium text-slate-700">
        <slot />
    </label>
</template>
