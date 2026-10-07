<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
});

const emit = defineEmits(['close']);

const panel = ref(null);

function onKeydown(event) {
    if (event.key === 'Escape' && props.open) {
        emit('close');
    }
}

watch(
    () => props.open,
    (isOpen) => {
        document.body.classList.toggle('overflow-hidden', isOpen);
    },
);

onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.classList.remove('overflow-hidden');
});
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50" @click="emit('close')" />

            <div
                ref="panel"
                role="dialog"
                aria-modal="true"
                class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl"
            >
                <h2 v-if="title" class="text-base font-semibold text-slate-900">{{ title }}</h2>

                <div class="mt-2 text-sm text-slate-600">
                    <slot />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Teleport>
</template>
