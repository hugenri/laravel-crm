<script setup>
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from './ui/Button.vue';
import Modal from './ui/Modal.vue';

/**
 * Acciones destructivas usan una petición Inertia.
 * El servidor valida los permisos.
 */
const props = defineProps({
    title: { type: String, required: true },
    message: { type: String, required: true },
    url: { type: String, required: true },
    method: { type: String, default: 'delete' },
    confirmLabel: { type: String, default: 'Eliminar' },
});

const open = ref(false);
const processing = ref(false);

function confirm() {
    processing.value = true;

    router[props.method](props.url, {}, {
        onFinish: () => {
            processing.value = false;
            open.value = false;
        },
    });
}
</script>

<template>
    <Button variant="danger" size="sm" @click="open = true">
        <slot />
    </Button>

    <Modal :open="open" :title="title" @close="open = false">
        <p>{{ message }}</p>

        <template #footer>
            <Button variant="secondary" :disabled="processing" @click="open = false">
                Cancelar
            </Button>
            <Button variant="danger" :disabled="processing" @click="confirm">
                {{ processing ? 'Procesando…' : confirmLabel }}
            </Button>
        </template>
    </Modal>
</template>
