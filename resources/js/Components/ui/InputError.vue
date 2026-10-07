<script setup>
defineProps({
    message: { type: String, default: null },
});
</script>

<template>
    <p v-if="message" class="mt-1.5 text-sm text-red-600">
        {{ message }}
    </p>
</template>
