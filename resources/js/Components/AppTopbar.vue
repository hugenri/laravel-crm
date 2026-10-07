<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useAuth } from '../composables/useAuth';
import { routes } from '../lib/routes';

const emit = defineEmits(['toggle-sidebar']);

const page = usePage();
const { user, initials } = useAuth();

const open = ref(false);
const loggingOut = ref(false);

function close() {
    open.value = false;
}

function submitLogout() {
    close();
    loggingOut.value = true;

    router.post(routes.auth.logout(), {}, { onFinish: () => (loggingOut.value = false) });
}
</script>

<template>
    <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-4 border-b border-slate-200 bg-white px-4 sm:px-6">
        <button
            type="button"
            class="-ml-1 rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
            aria-label="Abrir menú"
            @click="emit('toggle-sidebar')"
        >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
        </button>

        <div class="flex-1" />

        <div v-if="user" class="relative">
            <button
                type="button"
                class="flex items-center gap-2 rounded-lg py-1 pl-1 pr-2 text-sm hover:bg-slate-100"
                aria-haspopup="menu"
                :aria-expanded="open"
                @click="open = !open"
            >
                <span class="grid h-8 w-8 place-items-center rounded-full bg-brand-600 text-xs font-semibold text-white">
                    {{ initials }}
                </span>
                <span class="hidden max-w-40 truncate font-medium text-slate-700 sm:block">{{ user.name }}</span>
                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
            </button>

            <div
                v-show="open"
                class="absolute right-0 mt-2 w-56 overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-slate-200"
                role="menu"
            >
                <div class="border-b border-slate-100 px-4 py-3">
                    <p class="truncate text-sm font-medium text-slate-900">{{ user.name }}</p>
                    <p class="truncate text-xs text-slate-500">{{ user.email }}</p>
                </div>

                <Link
                    :href="routes.profile.edit()"
                    class="block px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50"
                    role="menuitem"
                    @click="close"
                >
                    Mi perfil
                </Link>

                <button
                    type="button"
                    class="block w-full px-4 py-2.5 text-left text-sm text-red-600 hover:bg-slate-50 disabled:opacity-50"
                    role="menuitem"
                    :disabled="loggingOut"
                    @click="submitLogout"
                >
                    {{ loggingOut ? 'Cerrando sesión…' : 'Cerrar sesión' }}
                </button>
            </div>
        </div>

        <span v-else class="text-sm text-slate-500">{{ page.props.appName }}</span>
    </header>
</template>
