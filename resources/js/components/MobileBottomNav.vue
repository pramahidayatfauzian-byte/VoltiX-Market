<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    HandCoins,
    History,
    LayoutDashboard,
    LayoutGrid,
    ReceiptText,
    RotateCcw,
    Settings,
    ShoppingBag,
    ShoppingCart,
    Truck,
    Users,
    UserCog,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();
const { isCurrentUrl } = useCurrentUrl();

const role = computed(() => (page.props.auth.user?.role as string | undefined) ?? '');
const isKasir = computed(() => role.value === 'kasir');

type NavGroup = { label: string; items: NavItem[] };

const navGroups = computed<NavGroup[]>(() => {
    const operasional: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutDashboard },
        { title: 'Kasir / Transaksi', href: '/kasir', icon: ReceiptText },
        { title: 'Riwayat', href: '/laporan', icon: History },
        { title: 'Piutang', href: '/piutang', icon: HandCoins },
        { title: 'Retur', href: '/retur', icon: RotateCcw },
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

const datar = computed(() => navGroups.value.flatMap((g) => g.items));

// 4 menu utama + 1 tombol "Lainnya" = 5 di bar bawah.
// Urutan mobile: Beranda, Produk, Kasir (tengah & diperbesar), Riwayat.
// Riwayat = /laporan (khusus kasir disembunyikan karena backend 403, fallback ke Pelanggan).
const judulUtama = computed(() =>
    isKasir.value
        ? ['Dashboard', 'Produk', 'Kasir / Transaksi', 'Pelanggan']
        : ['Dashboard', 'Produk', 'Kasir / Transaksi', 'Riwayat'],
);

const utama = computed(() =>
    judulUtama.value
        .map((t) => datar.value.find((m) => m.title === t))
        .filter((m): m is NavItem => !!m),
);

const sisaGroups = computed<NavGroup[]>(() =>
    navGroups.value
        .map((g) => ({
            label: g.label,
            items: g.items.filter((m) => !judulUtama.value.includes(m.title)),
        }))
        .filter((g) => g.items.length > 0),
);

const labelPendek: Record<string, string> = {
    'Dashboard': 'Beranda',
    'Kasir / Transaksi': 'Kasir',
    'Pembelian': 'Beli',
};

const pendek = (t: string) => labelPendek[t] ?? t;

function aktif(href: NonNullable<NavItem['href']>): boolean {
    const target = toUrl(href);
    if (target.includes('?')) {
        const norm = (u: string) => (u.startsWith('/') ? u : `/${u}`);
        return norm(page.url) === norm(target);
    }
    return isCurrentUrl(href);
}

const lainnyaAktif = computed(() =>
    sisaGroups.value.some((g) => g.items.some((m) => aktif(m.href))),
);

const terbuka = ref(false);
// Tutup sheet otomatis setelah pindah halaman.
watch(() => page.url, () => {
    terbuka.value = false;
});
</script>

<template>
    <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-neutral-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden">
        <div class="grid items-end" :style="{ gridTemplateColumns: `repeat(${utama.length + (sisaGroups.length > 0 ? 1 : 0)}, minmax(0, 1fr))` }">
            <template v-for="m in utama" :key="m.title">
                <!-- Menu Kasir diperbesar: tombol tengah menonjol (FAB) -->
                <Link
                    v-if="m.title === 'Kasir / Transaksi'"
                    :href="m.href"
                    class="flex flex-col items-center gap-1 pb-1.5 text-[12px] font-bold transition"
                    :class="aktif(m.href) ? 'text-emerald-700' : 'text-emerald-600'"
                >
                    <span
                        class="-mt-6 flex h-14 w-14 items-center justify-center rounded-full shadow-lg ring-4 ring-white transition active:scale-95"
                        :class="aktif(m.href) ? 'bg-emerald-700 text-white shadow-emerald-700/40' : 'bg-emerald-600 text-white shadow-emerald-600/40'"
                    >
                        <component :is="m.icon" class="h-7 w-7" stroke-width="2.25" />
                    </span>
                    <span class="max-w-full truncate leading-none">{{ pendek(m.title) }}</span>
                    <span class="h-1 w-8 rounded-full" :class="aktif(m.href) ? 'bg-emerald-600' : 'bg-transparent'" />
                </Link>
                <Link
                    v-else
                    :href="m.href"
                    class="flex flex-col items-center gap-1 py-2 text-[11px] font-medium transition"
                    :class="aktif(m.href) ? 'text-emerald-700' : 'text-neutral-500'"
                >
                    <component :is="m.icon" class="h-5 w-5" :class="aktif(m.href) ? 'text-emerald-600' : 'text-neutral-400'" />
                    <span class="max-w-full truncate">{{ pendek(m.title) }}</span>
                    <span class="h-1 w-8 rounded-full" :class="aktif(m.href) ? 'bg-emerald-600' : 'bg-transparent'" />
                </Link>
            </template>
            <button
                v-if="sisaGroups.length > 0"
                type="button"
                class="flex flex-col items-center gap-1 py-2 text-[11px] font-medium transition"
                :class="lainnyaAktif || terbuka ? 'text-emerald-700' : 'text-neutral-500'"
                @click="terbuka = true"
            >
                <LayoutGrid class="h-5 w-5" :class="lainnyaAktif || terbuka ? 'text-emerald-600' : 'text-neutral-400'" />
                <span>Lainnya</span>
                <span class="h-1 w-8 rounded-full" :class="lainnyaAktif ? 'bg-emerald-600' : 'bg-transparent'" />
            </button>
        </div>

        <Sheet v-model:open="terbuka">
            <SheetContent side="bottom" class="max-h-[75vh] overflow-y-auto rounded-t-2xl">
                <SheetHeader class="text-left">
                    <SheetTitle>Semua Menu</SheetTitle>
                </SheetHeader>
                <div v-for="g in sisaGroups" :key="g.label" class="mt-2">
                    <p class="px-1 py-1 text-[11px] font-semibold tracking-widest text-neutral-400 uppercase">{{ g.label }}</p>
                    <div class="grid grid-cols-4 gap-1">
                        <Link
                            v-for="m in g.items"
                            :key="m.title"
                            :href="m.href"
                            class="flex flex-col items-center gap-1.5 rounded-xl px-1 py-3 text-[11px] font-medium transition"
                            :class="aktif(m.href) ? 'bg-teal-50 text-emerald-700' : 'text-neutral-600 hover:bg-neutral-100'"
                        >
                            <component :is="m.icon" class="h-5 w-5" :class="aktif(m.href) ? 'text-emerald-600' : 'text-neutral-400'" />
                            <span class="max-w-full text-center leading-tight">{{ m.title }}</span>
                        </Link>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    </nav>
</template>
