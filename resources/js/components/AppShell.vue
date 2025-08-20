<script setup lang="ts">
import { SidebarProvider } from '@/components/ui/sidebar';
import { usePage } from '@inertiajs/vue3';
import Flash from '@/components/Flash.vue';

interface Props {
    variant?: 'header' | 'sidebar';
}

defineProps<Props>();

const isOpen = usePage().props.sidebarOpen;
</script>

<template>
    <div v-if="variant === 'header'" class="flex min-h-screen w-full flex-col">
        <Flash />
        <slot />
    </div>
    <SidebarProvider v-else :default-open="isOpen">
        <Flash />
        <slot />
    </SidebarProvider>
</template>
