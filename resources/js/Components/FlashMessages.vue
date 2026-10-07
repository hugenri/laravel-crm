<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Muestra mensajes flash (éxito o error) de la sesión.
 */
const page = usePage();

const messages = computed(() => {
    const { success, error } = page.props.flash ?? {};

    return [
        success && { key: 'success', text: success, classes: 'bg-emerald-50 text-emerald-800 ring-emerald-200' },
        error && { key: 'error', text: error, classes: 'bg-red-50 text-red-800 ring-red-200' },
    ].filter(Boolean);
});
</script>

<template>
    <div v-if="messages.length" class="mb-5 space-y-3">
        <div
            v-for="message in messages"
            :key="message.key"
            role="status"
            class="rounded-lg px-4 py-3 text-sm ring-1 ring-inset"
            :class="message.classes"
        >
            {{ message.text }}
        </div>
    </div>
</template>
