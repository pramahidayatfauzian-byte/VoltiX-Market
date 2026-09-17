<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';
import { computed } from 'vue';

withDefaults(
    defineProps<{
        items: NavItem[];
        label?: string;
    }>(),
    { label: '' },
);

const { isCurrentUrl } = useCurrentUrl();
const page = usePage();

// URL penuh saat ini termasuk query (mis. "/pembelian?tab=supplier").
const fullUrl = computed(() => page.url);

function isActive(href: NonNullable<NavItem['href']>): boolean {
    const target = toUrl(href);
    // Menu dengan query ?tab=... harus cocok persis supaya
    // "Pembelian" vs "Supplier" tidak aktif bersamaan.
    if (target.includes('?')) {
        const norm = (u: string) => (u.startsWith('/') ? u : `/${u}`);
        return norm(fullUrl.value) === norm(target);
    }
    return isCurrentUrl(href);
}
</script>

<template>
    <SidebarGroup class="px-0 py-1">
        <p v-if="label" class="px-3 py-1.5 text-[11px] font-semibold tracking-widest text-white/60 uppercase group-data-[collapsible=icon]:hidden">
            {{ label }}
        </p>
        <SidebarMenu class="gap-0.5">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="item.isActive ?? isActive(item.href)"
                    :tooltip="item.title"
                    class="rounded-md px-3 py-2.5 text-white/90 hover:bg-white/10 hover:text-white data-[active=true]:bg-black/15 data-[active=true]:font-semibold data-[active=true]:text-white data-[active=true]:shadow-[inset_-4px_0_0_0_var(--color-yellow-400)]"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
