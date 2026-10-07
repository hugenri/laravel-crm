<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: null },
});
</script>

<template>
    <header class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold tracking-tight text-slate-900">{{ title }}</h1>
            <p v-if="description" class="mt-1 text-sm text-slate-500">{{ description }}</p>
        </div>

        <div v-if="$slots.actions" class="flex items-center gap-3">
            <slot name="actions" />
        </div>
    </header>
</template>
