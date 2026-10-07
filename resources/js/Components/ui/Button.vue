<script setup>
import { computed, useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: (value) => ['primary', 'secondary', 'danger', 'ghost'].includes(value),
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md'].includes(value),
    },
    type: {
        type: String,
        default: 'button',
    },
});

const attrs = useAttrs();

const variants = {
    primary: 'bg-brand-600 text-white shadow-sm hover:bg-brand-700 focus-visible:outline-brand-600',
    secondary: 'bg-white text-slate-700 ring-1 ring-slate-300 shadow-sm hover:bg-slate-50 focus-visible:outline-slate-400',
    danger: 'bg-red-600 text-white shadow-sm hover:bg-red-700 focus-visible:outline-red-600',
    ghost: 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-slate-400',
};

const sizes = {
    sm: 'px-2.5 py-1.5 text-xs',
    md: 'px-3.5 py-2 text-sm',
};

const classes = computed(() => [
    'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition',
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2',
    'disabled:cursor-not-allowed disabled:opacity-50',
    variants[props.variant],
    sizes[props.size],
]);
</script>

<template>
    <button :type="type" :class="classes" v-bind="attrs">
        <slot />
    </button>
</template>
