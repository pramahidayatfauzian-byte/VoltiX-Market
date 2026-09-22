<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Bell,
    CheckCircle,
    ChevronDown,
    HandCoins,
    LogOut,
    Moon,
    RotateCcw,
    School,
    ShoppingCart,
    Sun,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { logout } from '@/routes';
import UserInfo from '@/components/UserInfo.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useKeranjang } from '@/composables/useKeranjang';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const user = computed(() => page.props.auth.user);
const bisaPindah = computed(() => (page.props.bisa_pindah_sekolah as boolean) ?? false);
const { qty: keranjangQty } = useKeranjang();
const sekolahAktif = computed(
    () => page.props.sekolah_aktif as { id_sekolah: number | null; nama_sekolah: string } | null,
);
const daftarSekolah = computed(
    () => (page.props.daftar_sekolah as Array<{ id_sekolah: number; nama_sekolah: string }>) ?? [],
);
const sekolahNama = computed(
    () => sekolahAktif.value?.nama_sekolah ?? user.value?.sekolah?.nama_sekolah ?? 'VOLTIX',
);
const sekolahLogo = computed(
    () => (user.value?.sekolah as Record<string, unknown> | undefined)?.logo_url as string | undefined,
);
const namaUser = computed(
    () => user.value?.nama_lengkap || user.value?.username || 'User',
);
const roleUser = computed(() => (user.value?.role as string | undefined) ?? '');
const bisaPengaturan = computed(() => ['developer', 'super admin'].includes(roleUser.value));

const { isDark, toggleAppearance } = useAppearance();
function toggleTema() {
    toggleAppearance();
}

// Taburan cahaya bintang seperti di sidebar.
const bintangHeader = [
    { l: '4%', t: '20%', s: 2, d: '0s', dur: '2.6s' },
    { l: '18%', t: '70%', s: 2, d: '0.9s', dur: '3.2s' },
    { l: '32%', t: '25%', s: 3, d: '1.5s', dur: '2.8s' },
    { l: '48%', t: '65%', s: 2, d: '0.4s', dur: '3.6s' },
    { l: '62%', t: '20%', s: 2, d: '2.1s', dur: '2.5s' },
    { l: '76%', t: '70%', s: 3, d: '1.1s', dur: '3.9s' },
    { l: '88%', t: '30%', s: 2, d: '0.2s', dur: '2.9s' },
    { l: '95%', t: '65%', s: 2, d: '1.8s', dur: '3.4s' },
];

function gantiSekolah(id: number | 'semua') {
    router.post('/sekolah-aktif', { id_sekolah: id }, { preserveScroll: true });
}

type NotifItem = {
    judul: string;
    teks: string;
    href: string | null;
    ikon: string;
    warna: string;
};
const notifTerbuka = ref(false);
const notifItems = ref<NotifItem[]>([]);
const notifTotal = ref(0);
const ikonMap: Record<string, unknown> = {
    TriangleAlert,
    HandCoins,
    RotateCcw,
    CheckCircle,
};

async function muatNotif() {
    try {
        const r = await fetch('/notifikasi', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const j = await r.json();
        notifItems.value = j.items ?? [];
        notifTotal.value = j.total ?? 0;
    } catch { /* abaikan */ }
}

watch(notifTerbuka, (buka) => {
    if (buka) muatNotif();
});
</script>

<template>
    <header
        class="anim-header-masuk relative flex h-16 shrink-0 items-center justify-between gap-2 overflow-hidden border-b border-white/15 bg-sidebar px-4 text-white transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-6"
    >
        <div aria-hidden="true" class="pointer-events-none absolute inset-0">
            <span
                v-for="(b, i) in bintangHeader"
                :key="i"
                class="bintang-header absolute rounded-full bg-white"
                :style="{ left: b.l, top: b.t, width: b.s + 'px', height: b.s + 'px', animationDelay: b.d, animationDuration: b.dur }"
            />
        </div>
        <div class="relative flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1 shrink-0 rounded-full text-white/80 transition hover:bg-white/10 hover:text-white" />
            <DropdownMenu v-if="bisaPindah">
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="hidden max-w-56 shrink-0 items-center gap-2 truncate rounded-full bg-white/15 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/25 sm:inline-flex"
                    >
                        <img v-if="sekolahLogo" :src="sekolahLogo" alt="Logo" class="h-5 w-5 shrink-0 rounded-full object-cover" />
                        <School v-else class="h-3.5 w-3.5 shrink-0" />
                        <span class="truncate">{{ sekolahNama }}</span>
                        <ChevronDown class="h-3.5 w-3.5 shrink-0 opacity-60" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" class="w-64">
                    <DropdownMenuLabel>Pindah Sekolah</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        :class="sekolahAktif?.id_sekolah === null ? 'bg-teal-50 font-semibold text-teal-700' : ''"
                        @click="gantiSekolah('semua')"
                    >
                        Semua Sekolah
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        v-for="s in daftarSekolah"
                        :key="s.id_sekolah"
                        :class="sekolahAktif?.id_sekolah === s.id_sekolah ? 'bg-teal-50 font-semibold text-teal-700' : ''"
                        @click="gantiSekolah(s.id_sekolah)"
                    >
                        {{ s.nama_sekolah }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <span
                v-else
                class="hidden max-w-56 shrink-0 items-center gap-2 truncate rounded-full bg-white/15 px-3 py-1.5 text-xs font-semibold text-white sm:inline-flex"
            >
                <img v-if="sekolahLogo" :src="sekolahLogo" alt="Logo" class="h-5 w-5 shrink-0 rounded-full object-cover" />
                <School v-else class="h-3.5 w-3.5 shrink-0" />
                <span class="truncate">{{ sekolahNama }}</span>
            </span>
        </div>

        <div class="relative flex items-center gap-2">
            <button
                type="button"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-white/85 transition hover:bg-white/10 hover:text-white"
                :aria-label="isDark ? 'Mode terang' : 'Mode gelap'"
                :title="isDark ? 'Mode terang' : 'Mode gelap'"
                @click="toggleTema()"
            >
                <Sun v-if="isDark" class="h-5 w-5" />
                <Moon v-else class="h-5 w-5" />
            </button>

            <Link
                href="/kasir"
                class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-white/85 transition hover:bg-white/10 hover:text-white"
                aria-label="Keranjang"
            >
                <ShoppingCart class="h-5 w-5" />
                <span
                    v-if="keranjangQty > 0"
                    class="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-teal-600 px-1 text-[11px] font-bold text-white ring-2 ring-white"
                >
                    {{ keranjangQty > 99 ? '99+' : keranjangQty }}
                </span>
            </Link>

            <DropdownMenu v-model:open="notifTerbuka">
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-white/85 transition hover:bg-white/10 hover:text-white"
                        aria-label="Notifikasi"
                    >
                        <Bell class="h-5 w-5" />
                        <span
                            v-if="notifTotal > 0"
                            class="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[11px] font-bold text-white ring-2 ring-white"
                        >
                            {{ notifTotal > 9 ? '9+' : notifTotal }}
                        </span>
                        <span
                            v-else
                            class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-red-500 ring-2 ring-white"
                        />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-80 p-0">
                    <DropdownMenuLabel class="px-3 py-2">Notifikasi</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <div class="max-h-80 overflow-y-auto">
                        <Link
                            v-for="(n, i) in notifItems"
                            :key="i"
                            :href="n.href ?? '#'"
                            class="flex items-start gap-2.5 px-3 py-2.5 text-sm hover:bg-teal-50"
                        >
                            <component
                                :is="ikonMap[n.ikon] ?? Bell"
                                class="mt-0.5 h-4 w-4 shrink-0"
                                :class="
                                    n.warna === 'red'
                                        ? 'text-red-500'
                                        : n.warna === 'amber'
                                          ? 'text-amber-500'
                                          : n.warna === 'sky'
                                            ? 'text-sky-500'
                                            : 'text-teal-600'
                                "
                            />
                            <span class="min-w-0">
                                <span class="block font-semibold text-neutral-800">{{ n.judul }}</span>
                                <span class="block text-xs text-neutral-500">{{ n.teks }}</span>
                            </span>
                        </Link>
                        <p
                            v-if="notifItems.length === 0"
                            class="px-3 py-6 text-center text-sm text-neutral-400"
                        >
                            Memuat...
                        </p>
                    </div>
                </DropdownMenuContent>
            </DropdownMenu>

            <div class="flex shrink-0 items-center gap-1 rounded-full border border-white/15 bg-white/10 py-1 pr-1 pl-1 shadow-xs">
                <Link
                    v-if="bisaPengaturan"
                    href="/pengaturan"
                    class="flex min-w-0 items-center gap-2 rounded-full py-0.5 pr-1.5 pl-0.5 transition hover:bg-white/10"
                    title="Pengaturan akun"
                >
                    <UserInfo :user="user" />
                    <span class="hidden min-w-0 text-left leading-tight sm:block">
                        <span class="block max-w-48 truncate text-sm font-bold text-white">{{ namaUser }}</span>
                        <span class="block text-[11px] font-medium text-amber-200 capitalize">{{ roleUser }}</span>
                    </span>
                </Link>
                <span
                    v-else
                    class="flex min-w-0 items-center gap-2 rounded-full py-0.5 pr-1.5 pl-0.5"
                    title="Profil"
                >
                    <UserInfo :user="user" />
                    <span class="hidden min-w-0 text-left leading-tight sm:block">
                        <span class="block max-w-48 truncate text-sm font-bold text-white">{{ namaUser }}</span>
                        <span class="block text-[11px] font-medium text-amber-200 capitalize">{{ roleUser }}</span>
                    </span>
                </span>
                <Link
                    :href="logout()"
                    method="post"
                    as="button"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white/70 transition hover:bg-red-500/25 hover:text-white"
                    title="Keluar"
                    aria-label="Keluar"
                    data-test="logout-button"
                    @click="router.flushAll()"
                >
                    <LogOut class="h-4 w-4" />
                </Link>
            </div>
        </div>
    </header>
</template>

<style scoped>
@keyframes header-masuk {
    from {
        opacity: 0;
        transform: translateY(-12px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.anim-header-masuk {
    animation: header-masuk 0.45s ease-out both;
}
@keyframes bintang-header-kedip {
    0%, 100% {
        opacity: 0.1;
        transform: scale(0.6);
    }
    50% {
        opacity: 0.9;
        transform: scale(1.15);
    }
}
.bintang-header {
    animation: bintang-header-kedip 3s ease-in-out infinite;
    box-shadow: 0 0 6px rgba(255, 255, 255, 0.7);
}
@media (prefers-reduced-motion: reduce) {
    .anim-header-masuk,
    .bintang-header {
        animation: none;
    }
}
</style>
