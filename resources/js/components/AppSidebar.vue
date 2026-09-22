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

// Taburan cahaya bintang di sidebar (dekoratif, pointer-events-none).
const bintang = [
    { l: '6%', t: '6%', s: 2, c: 'bg-white', d: '0s', dur: '2.4s' },
    { l: '88%', t: '4%', s: 2, c: 'bg-amber-200', d: '0.6s', dur: '3.1s' },
    { l: '72%', t: '10%', s: 3, c: 'bg-white', d: '1.2s', dur: '2.8s' },
    { l: '20%', t: '16%', s: 2, c: 'bg-teal-100', d: '0.3s', dur: '3.6s' },
    { l: '45%', t: '8%', s: 2, c: 'bg-white', d: '1.8s', dur: '2.2s' },
    { l: '92%', t: '22%', s: 3, c: 'bg-white', d: '0.9s', dur: '3.9s' },
    { l: '12%', t: '30%', s: 2, c: 'bg-amber-100', d: '2.1s', dur: '2.7s' },
    { l: '60%', t: '26%', s: 2, c: 'bg-white', d: '0.1s', dur: '3.3s' },
    { l: '80%', t: '36%', s: 2, c: 'bg-teal-100', d: '1.5s', dur: '2.5s' },
    { l: '30%', t: '42%', s: 3, c: 'bg-white', d: '0.7s', dur: '4.1s' },
    { l: '55%', t: '48%', s: 2, c: 'bg-amber-200', d: '2.4s', dur: '3s' },
    { l: '8%', t: '55%', s: 2, c: 'bg-white', d: '1.1s', dur: '2.9s' },
    { l: '90%', t: '58%', s: 2, c: 'bg-white', d: '0.4s', dur: '3.4s' },
    { l: '38%', t: '62%', s: 2, c: 'bg-teal-100', d: '1.9s', dur: '2.6s' },
    { l: '66%', t: '66%', s: 3, c: 'bg-white', d: '0.2s', dur: '3.8s' },
    { l: '16%', t: '72%', s: 2, c: 'bg-white', d: '2.7s', dur: '3.2s' },
    { l: '82%', t: '76%', s: 2, c: 'bg-amber-100', d: '1.4s', dur: '2.3s' },
    { l: '48%', t: '80%', s: 2, c: 'bg-white', d: '0.8s', dur: '4.3s' },
    { l: '5%', t: '86%', s: 3, c: 'bg-white', d: '2.2s', dur: '3s' },
    { l: '70%', t: '88%', s: 2, c: 'bg-teal-100', d: '1.6s', dur: '2.8s' },
    { l: '93%', t: '92%', s: 2, c: 'bg-white', d: '0.5s', dur: '3.5s' },
    { l: '25%', t: '94%', s: 2, c: 'bg-amber-200', d: '2.9s', dur: '2.7s' },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar">
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
            <span
                v-for="(b, i) in bintang"
                :key="i"
                class="bintang absolute rounded-full"
                :class="b.c"
                :style="{ left: b.l, top: b.t, width: b.s + 'px', height: b.s + 'px', animationDelay: b.d, animationDuration: b.dur }"
            />
            <span class="bintang-jatuh absolute top-[16%] right-[8%] h-[2px] w-20 origin-right rounded-full bg-gradient-to-l from-white via-white/80 to-transparent" />
            <span class="bintang-jatuh bintang-jatuh-2 absolute top-[42%] right-[20%] h-[2px] w-14 origin-right rounded-full bg-gradient-to-l from-amber-100 via-amber-100/70 to-transparent" />
        </div>
        <SidebarHeader class="anim-side-masuk border-b border-white/15 px-3 py-3">
            <div class="flex items-center gap-2.5">
                <span class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-white/15 transition-transform duration-300 hover:rotate-6 hover:scale-105">
                    <img v-if="sekolahLogo" :src="sekolahLogo" alt="Logo" class="size-10 object-cover" />
                    <img v-else src="/logo-voltix-mark.png" alt="VOLTIX" class="size-10 object-cover" />
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
            <template v-for="(g, i) in navGroups" :key="g.label">
                <div class="anim-side-masuk" :style="{ animationDelay: `${0.1 + i * 0.09}s` }">
                    <NavMain :items="g.items" :label="g.label" />
                </div>
            </template>
        </SidebarContent>

        <SidebarFooter class="anim-side-masuk px-2 pb-2" style="animation-delay: 0.4s">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

<style scoped>
@keyframes side-masuk {
    from {
        opacity: 0;
        transform: translateX(-14px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}
.anim-side-masuk {
    animation: side-masuk 0.45s ease-out both;
}
@keyframes bintang-kedip {
    0%, 100% {
        opacity: 0.1;
        transform: scale(0.6);
    }
    50% {
        opacity: 0.9;
        transform: scale(1.15);
    }
}
.bintang {
    animation: bintang-kedip 3s ease-in-out infinite;
    box-shadow: 0 0 6px rgba(255, 255, 255, 0.7);
}
@keyframes bintang-jatuh-gerak {
    0%, 86% {
        opacity: 0;
        transform: translate(0, 0) rotate(-24deg);
    }
    90% {
        opacity: 0.9;
    }
    97%, 100% {
        opacity: 0;
        transform: translate(-190px, 95px) rotate(-24deg);
    }
}
.bintang-jatuh {
    animation: bintang-jatuh-gerak 9s linear infinite;
}
.bintang-jatuh-2 {
    animation-delay: 4.5s;
    animation-duration: 11s;
}
@media (prefers-reduced-motion: reduce) {
    .anim-side-masuk,
    .bintang,
    .bintang-jatuh {
        animation: none;
    }
}
</style>
