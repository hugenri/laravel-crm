<script setup>
defineProps({
    tone: {
        type: String,
        default: 'slate',
        validator: (value) => ['slate', 'green', 'red', 'amber', 'brand'].includes(value),
    },
});

const tones = {
    slate: 'bg-slate-100 text-slate-700 ring-slate-200',
    green: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    red: 'bg-red-50 text-red-700 ring-red-200',
    amber: 'bg-amber-50 text-amber-800 ring-amber-200',
    brand: 'bg-brand-50 text-brand-700 ring-brand-200',
};
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
        :class="tones[tone]"
    >
        <slot />
    </span>
</template>
