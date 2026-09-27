<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { SidebarInset } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    variant?: AppVariant;
    class?: string;
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'sidebar',
});
const className = computed(() => props.class);
const page = usePage();
</script>

<template>
    <SidebarInset v-if="props.variant === 'sidebar'" :class="className">
        <div :key="page.url" class="anim-konten-dark flex min-h-0 flex-1 flex-col">
            <slot />
        </div>
    </SidebarInset>
    <main
        v-else
        class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-xl"
        :class="className"
    >
        <div :key="page.url" class="anim-konten-dark flex min-h-0 flex-1 flex-col">
            <slot />
        </div>
    </main>
</template>
