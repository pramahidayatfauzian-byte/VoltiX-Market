<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    CheckCircle,
    Database,
    ReceiptText,
    School,
    ShieldCheck,
    ShoppingBag,
    TriangleAlert,
    TrendingDown,
    TrendingUp,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

type GrafikItem = {
    tanggal: string;
    label: string;
    total: number;
    transaksi: number;
};

type TransaksiItem = {
    no: number;
    id_penjualan: number;
    tanggal: string | null;
    total: number;
    kasir: string;
    status: string | null;
};

type StokItem = {
    id_barang: number;
    nama: string | null;
    stok: number;
    satuan: string | null;
    habis: boolean;
};

const props = defineProps<{
    sekolah?: { id_sekolah: number; nama_sekolah: string | null } | null;
    stats: {
        penjualan_hari_ini: number;
        transaksi_hari_ini: number;
        produk_terjual: number;
        pelanggan: number;
        is_super_admin: boolean;
        tren_penjualan: number | null;
        tren_transaksi: number | null;
    };
    grafik: GrafikItem[];
    transaksi_terbaru: TransaksiItem[];
    stok_menipis: StokItem[];
    batas_menipis: number;
    is_developer: boolean;
    sekolah_aktif_id: number | 'semua' | null;
    sistem?: {
        sekolah_list: Array<{
            id_sekolah: number;
            kode_sekolah: string | null;
            nama_sekolah: string | null;
            logo_url: string | null;
            is_active: boolean;
            total_pengguna: number;
            total_produk: number;
        }>;
        total_sekolah: number;
        total_pengguna: number;
        pengguna_peran: Record<string, number>;
        pengguna_aktif: number;
        pengguna_nonaktif: number;
        pengguna_peran_aktif: Record<string, number>;
        platform: {
            aplikasi: string;
            laravel: string;
            php: string;
            os: string;
            server: string;
            database: string;
            zona_waktu: string;
        };
        server: {
            operasional: boolean;
            php: string;
            database: string;
            ukuran_db: string;
            waktu: string;
        };
    } | null;
}>();

const page = usePage();
const role = computed(() => (page.props.auth?.user?.role as string | undefined) ?? '');
const canViewProduk = computed(() => !['kasir', 'super admin'].includes(role.value));
const pindahLoading = ref<number | string | null>(null);

const WARNA_PERAN = ['bg-emerald-500', 'bg-sky-500', 'bg-amber-500', 'bg-violet-500', 'bg-blue-500', 'bg-rose-500'];
const peranAktifList = computed(() =>
    Object.entries(props.sistem?.pengguna_peran_aktif ?? {})
        .sort((a, b) => Number(b[1]) - Number(a[1]))
        .map(([peran, jumlah], i) => ({
            peran,
            jumlah: Number(jumlah),
            warna: WARNA_PERAN[i % WARNA_PERAN.length],
        })),
);
const maksPeranAktif = computed(() => Math.max(1, ...peranAktifList.value.map((p) => p.jumlah)));
function pindahSekolah(id: number | 'semua') {
    pindahLoading.value = id;
    router.post('/sekolah-aktif', { id_sekolah: id }, {
        preserveScroll: true,
        onSuccess: () => {
            router.visit(page.url, { preserveScroll: true, preserveState: false });
        },
        onFinish: () => {
            pindahLoading.value = null;
        },
    });
}

const formatRp = (n: number) =>
    'Rp ' + Number(n || 0).toLocaleString('id-ID');

const formatSingkat = (n: number) =>
    n >= 1000000
        ? (n / 1000000).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' jt'
        : n >= 1000
          ? Math.round(n / 1000) + ' rb'
          : String(Math.round(n));

const badgeTren = (tren: number | null) =>
    tren === null || tren === undefined
        ? null
        : {
              teks: `${tren >= 0 ? '▲' : '▼'} ${Math.abs(tren).toLocaleString('id-ID')}%`,
              naik: tren >= 0,
          };

const cards = computed(() => [
    {
        title: 'Penjualan Hari Ini',
        value: formatRp(props.stats.penjualan_hari_ini),
        icon: Banknote,
        sub: `${props.stats.transaksi_hari_ini} transaksi hari ini`,
        tren: badgeTren(props.stats.tren_penjualan),
        card: 'border-emerald-100 bg-emerald-50',
        chip: 'bg-emerald-500 text-white',
        spark: props.grafik.map((g) => g.total),
        sparkWarna: '#14b8a6',
    },
    {
        title: 'Transaksi',
        value: String(props.stats.transaksi_hari_ini),
        icon: ReceiptText,
        sub: 'Transaksi hari ini',
        tren: badgeTren(props.stats.tren_transaksi),
        card: 'border-sky-100 bg-sky-50',
        chip: 'bg-sky-500 text-white',
        spark: props.grafik.map((g) => g.transaksi),
        sparkWarna: '#0ea5e9',
    },
    {
        title: 'Produk Terjual',
        value: String(props.stats.produk_terjual),
        icon: ShoppingBag,
        sub: 'Item terjual hari ini',
        tren: null,
        card: 'border-amber-100 bg-amber-50',
        chip: 'bg-amber-500 text-white',
        spark: null as number[] | null,
        sparkWarna: '#f59e0b',
    },
    {
        title: 'Pelanggan',
        value: String(props.stats.pelanggan),
        icon: Users,
        sub: props.stats.is_super_admin
            ? 'Total seluruh pelanggan'
            : 'Pelanggan tenant ini',
        tren: null,
        card: 'border-violet-100 bg-violet-50',
        chip: 'bg-violet-500 text-white',
        spark: null as number[] | null,
        sparkWarna: '#8b5cf6',
    },
]);

// Garis tren mini (sparkline) 7 hari untuk kartu.
function poinSpark(data: number[]): string {
    const maks = Math.max(...data);
    const min = Math.min(...data);
    const rentang = maks - min || 1;
    return data
        .map((v, i) => {
            const x = data.length <= 1 ? 50 : (i / (data.length - 1)) * 100;
            const y = 24 - ((v - min) / rentang) * 20;
            return `${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');
}

// ---------- Grafik garis ganda (omzet teal + transaksi kuning) ----------
const LW = 720;
const LH = 300;
const PAD = { kiri: 52, kanan: 14, atas: 14, bawah: 34 };

const maksTotal = computed(() => Math.max(1, ...props.grafik.map((g) => g.total)));
const maksTrx = computed(() => Math.max(1, ...props.grafik.map((g) => g.transaksi)));

const titik = (i: number, nilai: number, maks: number): [number, number] => {
    const n = Math.max(props.grafik.length - 1, 1);
    const x = PAD.kiri + (i * (LW - PAD.kiri - PAD.kanan)) / n;
    const y = LH - PAD.bawah - (nilai / maks) * (LH - PAD.atas - PAD.bawah);
    return [Math.round(x * 10) / 10, Math.round(y * 10) / 10];
};

function garisHalus(pts: Array<[number, number]>): string {
    if (pts.length === 0) return '';
    if (pts.length === 1) return `M ${pts[0][0]} ${pts[0][1]}`;
    let d = `M ${pts[0][0]} ${pts[0][1]}`;
    for (let i = 0; i < pts.length - 1; i++) {
        const p0 = pts[Math.max(0, i - 1)];
        const p1 = pts[i];
        const p2 = pts[i + 1];
        const p3 = pts[Math.min(pts.length - 1, i + 2)];
        const c1x = p1[0] + (p2[0] - p0[0]) / 6;
        const c1y = p1[1] + (p2[1] - p0[1]) / 6;
        const c2x = p2[0] - (p3[0] - p1[0]) / 6;
        const c2y = p2[1] - (p3[1] - p1[1]) / 6;
        d += ` C ${c1x} ${c1y}, ${c2x} ${c2y}, ${p2[0]} ${p2[1]}`;
    }
    return d;
}

const garisTotal = computed(() =>
    garisHalus(props.grafik.map((g, i) => titik(i, g.total, maksTotal.value))),
);
const garisTrx = computed(() =>
    garisHalus(props.grafik.map((g, i) => titik(i, g.transaksi, maksTrx.value))),
);
const garisGrid = computed(() =>
    [0, 1 / 3, 2 / 3, 1].map(
        (f) => LH - PAD.bawah - f * (LH - PAD.atas - PAD.bawah),
    ),
);
const sumbuX = computed(() =>
    props.grafik.map((_, i) => titik(i, 0, 1)[0]),
);
const areaTotal = computed(() => {
    if (garisTotal.value === '') return '';
    const n = props.grafik.length;
    const xAkhir = titik(n - 1, 0, 1)[0];
    const xAwal = titik(0, 0, 1)[0];
    const yDasar = LH - PAD.bawah;
    return `${garisTotal.value} L ${xAkhir} ${yDasar} L ${xAwal} ${yDasar} Z`;
});
const labelSumbuY = computed(() =>
    [0, 1 / 3, 2 / 3, 1].map((f) =>
        f === 0 ? '0' : formatSingkat(maksTotal.value * f),
    ),
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <template v-if="is_developer && sistem">
            <!-- Header sistem -->
            <div>
                <h1 class="text-xl font-bold tracking-tight text-emerald-800">
                    Dashboard Sistem
                </h1>
                <p class="mt-0.5 text-sm text-neutral-500">
                    Gambaran seluruh instansi & kesehatan sistem
                </p>
            </div>

            <!-- Kartu ringkasan sistem -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-sky-100 bg-sky-50 p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-xs font-medium text-neutral-500">Instansi Terdaftar</p>
                            <p class="mt-1 text-2xl font-extrabold tracking-tight text-neutral-900">{{ sistem.total_sekolah }}</p>
                            <p class="mt-1 truncate text-xs text-neutral-400">sekolah di sistem</p>
                        </div>
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sky-500 text-white">
                            <School class="h-5 w-5" />
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-xs font-medium text-neutral-500">Total Pengguna Sistem</p>
                            <p class="mt-1 text-2xl font-extrabold tracking-tight text-neutral-900">{{ sistem.total_pengguna }}</p>
                            <p class="mt-1 truncate text-xs text-neutral-400 capitalize">{{ Object.entries(sistem.pengguna_peran).map(([r, j]) => `${r}: ${j}`).join(' · ') || 'belum ada pengguna' }}</p>
                        </div>
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-500 text-white">
                            <Users class="h-5 w-5" />
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-violet-100 bg-violet-50 p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-xs font-medium text-neutral-500">Privasi & Isolasi Data</p>
                            <p class="mt-1 text-2xl font-extrabold tracking-tight text-violet-700">100%</p>
                            <p class="mt-1 truncate text-xs text-neutral-400">terproteksi per tenant</p>
                        </div>
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-violet-500 text-white">
                            <ShieldCheck class="h-5 w-5" />
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-xs font-medium text-neutral-500">Server & Database</p>
                            <p class="mt-1 flex items-center gap-1.5 text-lg font-extrabold" :class="sistem.server.operasional ? 'text-green-600' : 'text-red-600'">
                                <span class="relative flex h-2.5 w-2.5">
                                    <span v-if="sistem.server.operasional" class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75" />
                                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full" :class="sistem.server.operasional ? 'bg-green-500' : 'bg-red-500'" />
                                </span>
                                {{ sistem.server.operasional ? 'Normal' : 'Gangguan' }}
                            </p>
                            <p class="mt-1 truncate text-xs text-neutral-400">PHP {{ sistem.server.php }} · DB {{ sistem.server.ukuran_db }}</p>
                        </div>
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-teal-500 text-white">
                            <Database class="h-5 w-5" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Distribusi pengguna aktif + Platform -->
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-5">
                <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md xl:col-span-3">
                    <div class="h-1 bg-gradient-to-r from-emerald-500 via-teal-400 to-sky-400" />
                    <div class="p-4">
                        <h2 class="flex items-center gap-2 text-sm font-bold text-emerald-800">
                            <Users class="h-4 w-4" /> Distribusi Pengguna Aktif
                        </h2>
                        <div class="mt-3 flex flex-col items-center gap-4 sm:flex-row">
                            <div
                                class="relative h-36 w-36 shrink-0 rounded-full"
                                :style="{ background: `conic-gradient(#10b981 0 ${(sistem.total_pengguna > 0 ? (sistem.pengguna_aktif / sistem.total_pengguna) * 100 : 0).toFixed(1)}%, #e8ecea ${(sistem.total_pengguna > 0 ? (sistem.pengguna_aktif / sistem.total_pengguna) * 100 : 0).toFixed(1)}% 100%)` }"
                            >
                                <div class="absolute inset-3 flex flex-col items-center justify-center rounded-full bg-white dark:bg-neutral-900">
                                    <span class="text-xl font-extrabold text-neutral-900 dark:text-white">{{ sistem.pengguna_aktif }}</span>
                                    <span class="text-[11px] text-neutral-400">aktif / {{ sistem.total_pengguna }}</span>
                                </div>
                            </div>
                            <div class="w-full min-w-0 flex-1 space-y-2">
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-500" />
                                    <span class="flex-1 text-neutral-500">Aktif</span>
                                    <span class="font-bold text-neutral-800 dark:text-neutral-100">{{ sistem.pengguna_aktif }}</span>
                                </div>
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-neutral-300 dark:bg-neutral-700" />
                                    <span class="flex-1 text-neutral-500">Nonaktif</span>
                                    <span class="font-bold text-neutral-800 dark:text-neutral-100">{{ sistem.pengguna_nonaktif }}</span>
                                </div>
                                <div class="border-t border-neutral-100 pt-2 dark:border-neutral-800">
                                    <p class="mb-1.5 text-[11px] font-semibold tracking-widest text-neutral-400 uppercase">Aktif per peran</p>
                                    <div v-for="p in peranAktifList" :key="p.peran" class="mb-1.5">
                                        <div class="mb-0.5 flex items-center justify-between text-xs">
                                            <span class="text-neutral-500 capitalize">{{ p.peran }}</span>
                                            <span class="font-bold text-neutral-800 dark:text-neutral-100">{{ p.jumlah }}</span>
                                        </div>
                                        <div class="h-1.5 overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800">
                                            <div class="h-full rounded-full transition-all" :class="p.warna" :style="{ width: `${Math.max(4, (p.jumlah / maksPeranAktif) * 100)}%` }" />
                                        </div>
                                    </div>
                                    <p v-if="peranAktifList.length === 0" class="text-xs text-neutral-400">Belum ada pengguna aktif.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md xl:col-span-2">
                    <div class="h-1 bg-gradient-to-r from-sky-500 via-teal-400 to-emerald-500" />
                    <div class="p-4">
                        <h2 class="flex items-center gap-2 text-sm font-bold text-emerald-800">
                            <Database class="h-4 w-4" /> Informasi Platform
                        </h2>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div v-for="baris in [['Aplikasi', sistem.platform.aplikasi], ['Laravel', sistem.platform.laravel], ['PHP', sistem.platform.php], ['Sistem Operasi', sistem.platform.os], ['Web Server', sistem.platform.server], ['Database', sistem.platform.database], ['Zona Waktu', sistem.platform.zona_waktu]]" :key="baris[0]" class="flex items-center justify-between gap-3">
                                <dt class="shrink-0 text-neutral-400">{{ baris[0] }}</dt>
                                <dd class="max-w-55 truncate text-right font-mono text-xs font-semibold text-neutral-700 dark:text-neutral-200" :title="String(baris[1])">{{ baris[1] }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Pindah sekolah -->
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="flex items-center gap-2 text-sm font-bold text-emerald-800">
                        <School class="h-4 w-4" /> Instansi Sekolah Terdaftar ({{ sistem.total_sekolah }})
                    </h2>
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                        :class="(sekolah_aktif_id === 'semua' || sekolah_aktif_id === null) ? 'bg-emerald-600 text-white' : 'border border-neutral-200 text-neutral-600 hover:border-emerald-300 hover:text-emerald-700'"
                        :disabled="pindahLoading !== null"
                        @click="pindahSekolah('semua')"
                    >
                        Semua Sekolah
                    </button>
                </div>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="s in sistem.sekolah_list"
                        :key="s.id_sekolah"
                        class="rounded-xl border p-3 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
                        :class="sekolah_aktif_id === s.id_sekolah ? 'border-emerald-500 bg-emerald-50/60 shadow-sm' : 'border-neutral-200 hover:border-emerald-200'"
                    >
                        <div class="flex items-center gap-3">
                            <img v-if="s.logo_url" :src="s.logo_url" alt="Logo" class="h-11 w-11 shrink-0 rounded-lg border border-neutral-200 object-cover" />
                            <span v-else class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-emerald-600 to-teal-600 text-base font-bold text-white">
                                {{ (s.nama_sekolah ?? '?').trim().charAt(0).toUpperCase() }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold text-neutral-900">{{ s.nama_sekolah }}</p>
                                <p class="truncate font-mono text-xs text-neutral-400">{{ s.kode_sekolah ?? '-' }} · {{ s.total_pengguna }} pengguna · {{ s.total_produk }} produk</p>
                            </div>
                        </div>
                        <div class="mt-2.5 flex items-center justify-between gap-2">
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="s.is_active ? 'bg-green-100 text-green-700' : 'bg-neutral-100 text-neutral-500'">{{ s.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            <button
                                v-if="sekolah_aktif_id !== s.id_sekolah"
                                type="button"
                                class="h-9 rounded-lg bg-emerald-600 px-4 text-xs font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                                :disabled="pindahLoading !== null"
                                @click="pindahSekolah(s.id_sekolah)"
                            >
                                {{ pindahLoading === s.id_sekolah ? 'Memindah...' : 'Pindah' }}
                            </button>
                            <span v-else class="rounded-lg bg-emerald-100 px-3 py-1.5 text-xs font-bold text-emerald-700">Sedang Aktif</span>
                        </div>
                    </div>
                </div>
            </div>

        </template>
        <template v-else>
        <!-- Header -->
        <div>
            <h1 class="text-xl font-bold tracking-tight text-emerald-800">
                Dashboard
            </h1>
            <p class="mt-0.5 text-sm text-neutral-500">
                Ringkasan aktivitas penjualan hari ini
                <span v-if="sekolah?.nama_sekolah" class="font-medium text-neutral-700">
                    — {{ sekolah.nama_sekolah }}
                </span>
            </p>
        </div>

        <!-- Banner sambutan -->
        <div class="rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm shadow-sm">
            <p class="font-medium text-teal-800">
                Halo, selamat datang di VOLTIX<span v-if="sekolah?.nama_sekolah"> — {{ sekolah.nama_sekolah }}</span>!
            </p>
            <p class="mt-0.5 text-teal-600">
                Nikmati pengalaman kelola kasir sekolah lebih mudah dan cepat bersama kami.
            </p>
        </div>

        <!-- Peringatan stok menipis -->
        <div
            v-if="stok_menipis.length > 0"
            class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm"
        >
            <div class="flex items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 text-sm font-bold text-amber-800">
                    <TriangleAlert class="h-4 w-4" />
                    Stok Menipis (≤ {{ batas_menipis }})
                </h2>
                <a
                    v-if="canViewProduk"
                    href="/produk"
                    class="text-xs font-medium text-amber-700 hover:underline"
                >
                    Lihat produk →
                </a>
            </div>
            <ul class="mt-2 flex flex-wrap gap-2">
                <li
                    v-for="s in stok_menipis"
                    :key="s.id_barang"
                    class="rounded-lg bg-white px-2.5 py-1.5 text-xs shadow-xs"
                    :class="s.habis ? 'font-bold text-red-600' : 'text-neutral-700'"
                >
                    {{ s.nama }} — sisa {{ s.stok }} {{ s.satuan ?? '' }}
                </li>
            </ul>
        </div>

        <!-- 4 Cards -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="c in cards"
                :key="c.title"
                class="rounded-xl border p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
                :class="c.card"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-xs font-medium text-neutral-500">
                            {{ c.title }}
                        </p>
                        <p class="mt-1 truncate text-xl font-bold text-neutral-900">
                            {{ c.value }}
                        </p>
                        <p class="mt-1 flex items-center gap-1.5">
                            <span
                                v-if="c.tren"
                                class="inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 text-[11px] font-semibold"
                                :class="c.tren.naik ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'"
                            >
                                <component :is="c.tren.naik ? TrendingUp : TrendingDown" class="h-3 w-3" />
                                {{ c.tren.teks }}
                            </span>
                            <span class="truncate text-xs text-neutral-400">{{ c.sub }}</span>
                        </p>
                        <svg v-if="c.spark && c.spark.length > 1" viewBox="0 0 100 28" class="mt-2 h-7 w-full" preserveAspectRatio="none" aria-hidden="true">
                            <polyline :points="poinSpark(c.spark)" fill="none" :stroke="c.sparkWarna" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
                        :class="c.chip"
                    >
                        <component :is="c.icon" class="h-5 w-5" />
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-5">
            <!-- Grafik -->
            <div
                class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm xl:col-span-3"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-bold text-emerald-800">
                            Grafik Penjualan
                        </h2>
                        <p class="text-xs text-neutral-400">
                            7 hari terakhir (data live)
                        </p>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-neutral-500">
                        <span class="inline-flex items-center gap-1">
                            <span class="h-2 w-2 rounded-full bg-yellow-400" />
                            Total Penjualan
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <span class="h-2 w-2 rounded-full bg-teal-500" />
                            Total Transaksi
                        </span>
                    </div>
                </div>

                <div
                    v-if="grafik.every((g) => g.total === 0)"
                    class="mt-6 rounded-lg bg-neutral-50 px-4 py-8 text-center text-sm text-neutral-400"
                >
                    Belum ada penjualan dalam 7 hari terakhir untuk tenant ini.
                </div>

                <svg v-else :viewBox="`0 0 ${LW} ${LH}`" class="mt-2 h-auto w-full" role="img" aria-label="Grafik penjualan 7 hari">
                    <defs>
                        <linearGradient id="isiOmzet" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#eab308" stop-opacity="0.25" />
                            <stop offset="100%" stop-color="#eab308" stop-opacity="0" />
                        </linearGradient>
                    </defs>
                    <g v-for="x in sumbuX" :key="'v' + x">
                        <line :x1="x" :x2="x" :y1="PAD.atas" :y2="LH - PAD.bawah" class="stroke-neutral-100" stroke-width="1" stroke-dasharray="2 5" />
                    </g>
                    <g v-for="(gy, i) in garisGrid" :key="i">
                        <line :x1="PAD.kiri" :x2="LW - PAD.kanan" :y1="gy" :y2="gy" class="stroke-neutral-200" stroke-width="1" stroke-dasharray="5 4" />
                        <text :x="PAD.kiri - 8" :y="gy + 4" text-anchor="end" class="fill-neutral-400" font-size="11">
                            {{ labelSumbuY[i] }}
                        </text>
                    </g>
                    <path :d="areaTotal" fill="url(#isiOmzet)" stroke="none" />
                    <path :d="garisTotal" fill="none" stroke="#eab308" stroke-width="2.5" stroke-linecap="round" />
                    <path :d="garisTrx" fill="none" stroke="#14b8a6" stroke-width="2.5" stroke-linecap="round" />
                    <g v-for="(g, i) in grafik" :key="g.tanggal">
                        <circle :cx="titik(i, g.total, maksTotal)[0]" :cy="titik(i, g.total, maksTotal)[1]" r="4" fill="#eab308" stroke="#fff" stroke-width="2">
                            <title>{{ g.label }}: {{ formatRp(g.total) }} ({{ g.transaksi }} transaksi)</title>
                        </circle>
                        <circle :cx="titik(i, g.transaksi, maksTrx)[0]" :cy="titik(i, g.transaksi, maksTrx)[1]" r="4" fill="#14b8a6" stroke="#fff" stroke-width="2">
                            <title>{{ g.label }}: {{ formatRp(g.total) }} ({{ g.transaksi }} transaksi)</title>
                        </circle>
                        <text :x="titik(i, 0, 1)[0]" :y="LH - 10" text-anchor="middle" class="fill-neutral-400" font-size="11">
                            {{ g.label }}
                        </text>
                    </g>
                </svg>
            </div>

            <!-- Transaksi terbaru -->
            <div
                class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm xl:col-span-2"
            >
                <h2 class="text-sm font-bold text-emerald-800">
                    Transaksi Terbaru
                </h2>
                <p class="text-xs text-neutral-400">
                    Data langsung dari database
                </p>

                <div
                    v-if="transaksi_terbaru.length === 0"
                    class="mt-6 rounded-lg bg-neutral-50 px-4 py-8 text-center text-sm text-neutral-400"
                >
                    Belum ada transaksi.
                </div>

                <div v-else class="mt-3">
                <!-- Kartu mobile -->
                <div class="space-y-2 sm:hidden">
                    <div
                        v-for="t in transaksi_terbaru"
                        :key="t.id_penjualan"
                        class="rounded-lg border border-neutral-100 px-3 py-2.5 text-sm"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-bold text-neutral-900">{{ formatRp(t.total) }}</span>
                            <span class="max-w-28 truncate text-xs text-neutral-500">{{ t.kasir }}</span>
                        </div>
                        <p class="mt-1 text-xs whitespace-nowrap text-neutral-400">{{ t.tanggal ?? '-' }}</p>
                    </div>
                </div>

                <div class="hidden overflow-x-auto sm:block">
                    <table class="w-full min-w-105 text-left text-sm">
                        <thead>
                            <tr class="border-b border-neutral-100 text-xs text-neutral-400">
                                <th class="py-2 pr-2 font-medium">No</th>
                                <th class="py-2 pr-2 font-medium">Tanggal</th>
                                <th class="py-2 pr-2 text-right font-medium">Total</th>
                                <th class="py-2 font-medium">Kasir</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="t in transaksi_terbaru"
                                :key="t.id_penjualan"
                                class="border-b border-neutral-50 last:border-0 hover:bg-neutral-50"
                            >
                                <td class="py-2 pr-2 text-neutral-500">{{ t.no }}</td>
                                <td class="py-2 pr-2 whitespace-nowrap text-neutral-700">
                                    {{ t.tanggal ?? '-' }}
                                </td>
                                <td class="py-2 pr-2 text-right font-semibold whitespace-nowrap text-neutral-900">
                                    {{ formatRp(t.total) }}
                                </td>
                                <td class="max-w-28 truncate py-2 text-neutral-600">
                                    {{ t.kasir }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                </div>
            </div>
        </div>
        </template>
    </div>
</template>
