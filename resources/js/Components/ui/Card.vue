<script setup>
defineProps({
    title: { type: String, default: null },
    description: { type: String, default: null },
});
</script>

<template>
    <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <header v-if="title || description || $slots.header" class="border-b border-slate-200 px-5 py-4">
            <slot name="header">
                <h2 v-if="title" class="text-base font-semibold text-slate-900">{{ title }}</h2>
                <p v-if="description" class="mt-0.5 text-sm text-slate-500">{{ description }}</p>
            </slot>
        </header>

        <div class="px-5 py-4">
            <slot />
        </div>

        <footer v-if="$slots.footer" class="flex items-center gap-3 border-t border-slate-200 bg-slate-50 px-5 py-3">
            <slot name="footer" />
        </footer>
    </section>
</template>
