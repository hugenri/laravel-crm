<script setup>
import { ref } from 'vue';
import AppSidebar from '../Components/AppSidebar.vue';
import AppTopbar from '../Components/AppTopbar.vue';
import FlashMessages from '../Components/FlashMessages.vue';

const sidebarOpen = ref(false);
</script>

<template>
    <div class="min-h-screen lg:flex">
        <AppSidebar :open="sidebarOpen" @close="sidebarOpen = false" />

        <div class="flex min-w-0 flex-1 flex-col">
            <AppTopbar @toggle-sidebar="sidebarOpen = !sidebarOpen" />

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    <FlashMessages />
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
