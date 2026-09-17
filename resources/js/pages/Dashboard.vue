<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Banknote,
    ReceiptText,
    ShoppingBag,
    TriangleAlert,
    TrendingDown,
    TrendingUp,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
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
}>();

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
    },
    {
        title: 'Transaksi',
        value: String(props.stats.transaksi_hari_ini),
        icon: ReceiptText,
        sub: 'Transaksi hari ini',
        tren: badgeTren(props.stats.tren_transaksi),
        card: 'border-sky-100 bg-sky-50',
        chip: 'bg-sky-500 text-white',
    },
    {
        title: 'Produk Terjual',
        value: String(props.stats.produk_terjual),
        icon: ShoppingBag,
        sub: 'Item terjual hari ini',
        tren: null,
        card: 'border-amber-100 bg-amber-50',
        chip: 'bg-amber-500 text-white',
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
    },
]);

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
const labelSumbuY = computed(() =>
    [0, 1 / 3, 2 / 3, 1].map((f) =>
        f === 0 ? '0' : formatSingkat(maksTotal.value * f),
    ),
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
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
                <a href="/produk" class="text-xs font-medium text-amber-700 hover:underline">
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
                    <g v-for="(gy, i) in garisGrid" :key="i">
                        <line :x1="PAD.kiri" :x2="LW - PAD.kanan" :y1="gy" :y2="gy" class="stroke-neutral-100" stroke-width="1" />
                        <text :x="PAD.kiri - 8" :y="gy + 4" text-anchor="end" class="fill-neutral-400" font-size="11">
                            {{ labelSumbuY[i] }}
                        </text>
                    </g>
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
    </div>
</template>
