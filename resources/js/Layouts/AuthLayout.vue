<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import FlashMessages from '../Components/FlashMessages.vue';

const page = usePage();

const appName = computed(() => page.props.appName);
</script>

<template>
    <div class="flex min-h-screen flex-col justify-center bg-slate-100 px-4 py-12">
        <div class="mx-auto w-full max-w-md">
            <div class="mb-6 flex flex-col items-center">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-brand-600 text-lg font-bold text-white">
                    C
                </span>
                <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-900">{{ appName }}</h1>
            </div>

            <div class="rounded-xl bg-white px-6 py-8 shadow-sm ring-1 ring-slate-200">
                <FlashMessages />
                <slot />
            </div>
        </div>
    </div>
</template>
