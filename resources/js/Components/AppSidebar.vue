<script setup>
import { computed } from 'vue';
import { useAuth } from '../composables/useAuth';
import { usePage } from '@inertiajs/vue3';
import AppNavLinks from './AppNavLinks.vue';

defineProps({
    open: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const page = usePage();
const { user, initials } = useAuth();

const appName = computed(() => page.props.appName);
</script>

<template>
    <div>
        <div
            v-show="open"
            class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"
            @click="emit('close')"
        />

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-slate-900 transition-transform lg:static lg:translate-x-0"
            :class="open ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex h-16 shrink-0 items-center gap-2 border-b border-slate-800 px-5">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-600 text-sm font-bold text-white">
                    C
                </span>
                <span class="truncate text-sm font-semibold text-white">{{ appName }}</span>
            </div>

            <div class="flex-1 overflow-y-auto">
                <AppNavLinks @click="emit('close')" />
            </div>

            <div v-if="user" class="border-t border-slate-800 p-4">
                <div class="flex items-center gap-3">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-slate-700 text-xs font-semibold text-white">
                        {{ initials }}
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-white">{{ user.name }}</p>
                        <p class="truncate text-xs text-slate-400">{{ user.email }}</p>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</template>
