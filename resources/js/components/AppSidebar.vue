<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    HandCoins,
    History,
    LayoutDashboard,
    ReceiptText,
    RotateCcw,
    Settings,
    ShoppingBag,
    ShoppingCart,
    Truck,
    Zap,
    Users,
    UserCog,
} from '@lucide/vue';
import { computed } from 'vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();
const sekolahNama = computed(
    () => (page.props.auth.user?.sekolah as Record<string, unknown> | undefined)?.nama_sekolah as string | undefined ?? 'VOLTIX',
);
const sekolahLogo = computed(
    () => (page.props.auth.user?.sekolah as Record<string, unknown> | undefined)?.logo_url as string | undefined,
);

const role = computed(() => (page.props.auth.user?.role as string | undefined) ?? '');
const isKasir = computed(() => role.value === 'kasir');

type NavGroup = { label: string; items: NavItem[] };

const navGroups = computed<NavGroup[]>(() => {
    const operasional: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutDashboard },
        { title: 'Kasir / Transaksi', href: '/kasir', icon: ReceiptText },
        { title: 'Piutang', href: '/piutang', icon: HandCoins },
        { title: 'Retur', href: '/retur', icon: RotateCcw },
        { title: 'Laporan', href: '/laporan', icon: BarChart3 },
    ];
    const persediaan: NavItem[] = [
        { title: 'Produk', href: '/produk', icon: ShoppingBag },
        { title: 'Pembelian', href: '/pembelian', icon: ShoppingCart },
        { title: 'Supplier', href: '/supplier', icon: Truck },
    ];
    const administrasi: NavItem[] = [
        { title: 'Pelanggan', href: '/pelanggan', icon: Users },
        { title: 'User', href: '/user', icon: UserCog },
        { title: 'Audit Log', href: '/audit', icon: History },
        { title: 'Pengaturan', href: '/pengaturan', icon: Settings },
    ];

    const izinKasir = new Set(['Dashboard', 'Kasir / Transaksi', 'Produk', 'Pelanggan']);
    // Semua role kecuali kasir tampil semua menu (termasuk User & Pengaturan untuk admin).
    // Pembatasan aksi dilakukan di backend + tombol frontend:
    // - Admin: User read-only (tanpa edit/reset), Pengaturan 403.
    // - Kasir: hanya menu terbatas.

    const saring = (items: NavItem[]) => {
        if (isKasir.value) return items.filter((m) => izinKasir.has(m.title));
        return items;
    };

    return [
        { label: 'Operasional', items: saring(operasional) },
        { label: 'Persediaan', items: saring(persediaan) },
        { label: 'Administrasi', items: saring(administrasi) },
    ].filter((g) => g.items.length > 0);
});
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar">
        <SidebarHeader class="border-b border-white/15 px-3 py-3">
            <div class="flex items-center gap-2.5">
                <span class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-white/15">
                    <img v-if="sekolahLogo" :src="sekolahLogo" alt="Logo" class="size-10 object-cover" />
                    <Zap v-else class="size-5 text-white" />
                </span>
                <span class="min-w-0 flex-1 leading-tight group-data-[collapsible=icon]:hidden">
                    <span class="block text-sm font-bold tracking-tight text-white">VOLTIX</span>
                    <span class="block truncate text-[11px] text-white/70">
                        {{ sekolahNama }}
                    </span>
                </span>
                <SidebarTrigger class="ml-auto size-8 shrink-0 rounded-full bg-white text-teal-600 hover:bg-white/90 hover:text-teal-700 group-data-[collapsible=icon]:hidden" />
            </div>
        </SidebarHeader>

        <SidebarContent class="px-2">
            <template v-for="g in navGroups" :key="g.label">
                <NavMain :items="g.items" :label="g.label" />
            </template>
        </SidebarContent>

        <SidebarFooter class="px-2 pb-2">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
