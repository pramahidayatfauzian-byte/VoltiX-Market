<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import MobileBottomNav from '@/components/MobileBottomNav.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip bg-[#f3f5f9] pb-20 md:pb-0 dark:bg-neutral-950">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <MobileBottomNav />
        <ConfirmDialog />
        <Toaster position="bottom-center" :toast-options="{ style: { marginBottom: '140px' } }" />
    </AppShell>
</template>
