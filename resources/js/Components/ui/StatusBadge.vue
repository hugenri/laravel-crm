<script setup>
import Badge from './Badge.vue';

defineProps({
    isActive: { type: Boolean, required: true },
});
</script>

<template>
    <Badge :tone="isActive ? 'green' : 'red'">
        <span
            class="h-1.5 w-1.5 rounded-full"
            :class="isActive ? 'bg-emerald-500' : 'bg-red-500'"
        />
        {{ isActive ? 'Activo' : 'Inactivo' }}
    </Badge>
</template>
