<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    tone: {
        type: String,
        default: 'slate',
        validator: (value) => ['slate', 'green', 'red', 'amber', 'brand'].includes(value),
    },
});

const tones = {
    slate: 'text-slate-900',
    green: 'text-emerald-700',
    red: 'text-red-700',
    amber: 'text-amber-800',
    brand: 'text-brand-700',
};
</script>

<template>
    <div class="rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm text-slate-500">{{ label }}</p>
        <p class="mt-1 text-2xl font-semibold tracking-tight" :class="tones[tone]">
            {{ value }}
        </p>
    </div>
</template>