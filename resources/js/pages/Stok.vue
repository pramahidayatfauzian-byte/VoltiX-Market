<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Archive, Boxes, PackageX, TriangleAlert } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Stok', href: '/stok' }],
    },
});

type Kategori = { id_kategori: number; nama: string | null };

const props = defineProps<{
    sekolah?: { id_sekolah: number; nama_sekolah: string | null } | null;
    kategori_list: Kategori[];
    batas_menipis: number;
    is_super_admin: boolean;
}>();

type Row = {
    id_barang: number;
    barcode: string | null;
    nama: string | null;
    satuan: string | null;
    stok: number;
    harga_beli: number;
    total_masuk: number;
    total_keluar: number;
    kategori?: { nama: string | null } | null;
};

type Paginate<T> = { data: T[]; current_page: number; last_page: number; total: number };

const fSearch = ref('');
const fKategori = ref('');
const fKondisi = ref('');
const ringkasan = ref({ total_produk: 0, menipis: 0, habis: 0, nilai_stok: 0 });
const rows = ref<Row[]>([]);
const curPage = ref(1);
const lastPage = ref(1);
const totalBaris = ref(0);
const loading = ref(false);

async function muat() {
    loading.value = true;
    try {
        const q = new URLSearchParams({
            search: fSearch.value, id_kategori: fKategori.value,
            kondisi: fKondisi.value, page: String(curPage.value),
        });
        const r = await fetch(`/stok/data?${q}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const j = await r.json();
        ringkasan.value = j.ringkasan;
        rows.value = j.tabel.data;
        curPage.value = j.tabel.current_page;
        lastPage.value = j.tabel.last_page;
        totalBaris.value = j.tabel.total;
    } catch { /* abaikan */ } finally { loading.value = false; }
}

function terapkan() {
    curPage.value = 1;
    muat();
}

onMounted(() => muat());

const formatRp = (n: number) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');

const cards = computed(() => [
    { title: 'Total Produk Aktif', value: String(ringkasan.value.total_produk), icon: Boxes, card: 'border-sky-100 bg-sky-50', chip: 'bg-sky-500 text-white' },
    { title: `Stok Menipis (≤ ${props.batas_menipis})`, value: String(ringkasan.value.menipis), icon: TriangleAlert, card: 'border-amber-100 bg-amber-50', chip: 'bg-amber-500 text-white' },
    { title: 'Stok Habis', value: String(ringkasan.value.habis), icon: PackageX, card: 'border-red-100 bg-red-50', chip: 'bg-red-500 text-white' },
    { title: 'Nilai Persediaan', value: formatRp(ringkasan.value.nilai_stok), icon: Archive, card: 'border-emerald-100 bg-emerald-50', chip: 'bg-emerald-500 text-white' },
]);

const kondisiStok = (stok: number) =>
    stok <= 0 ? 'habis' : stok <= props.batas_menipis ? 'menipis' : 'aman';
</script>

<template>
    <Head title="Stok" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-emerald-800">Stok Persediaan</h1>
            <p class="mt-0.5 text-sm text-neutral-500">
                Stok terkini + arus masuk (pembelian selesai) & keluar (penjualan)
                <span v-if="sekolah?.nama_sekolah && !is_super_admin" class="font-medium text-neutral-700"> — {{ sekolah.nama_sekolah }}</span>
            </p>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="c in cards" :key="c.title" class="rounded-xl border p-4 shadow-sm" :class="c.card">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-xs font-medium text-neutral-500">{{ c.title }}</p>
                        <p class="mt-1 truncate text-xl font-bold text-neutral-900">{{ c.value }}</p>
                    </div>
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg" :class="c.chip">
                        <component :is="c.icon" class="h-5 w-5" />
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:flex md:flex-wrap md:items-end">
                <div class="min-w-0 sm:col-span-2 md:col-span-1">
                    <Label>Cari Produk</Label>
                    <div class="mt-1 flex gap-2">
                        <Input v-model="fSearch" placeholder="Nama / barcode... (Enter)" class="min-w-0 flex-1" @keydown.enter="terapkan()" />
                        <Button type="button" class="h-9 shrink-0 bg-emerald-600 px-4 hover:bg-emerald-700" @click="terapkan()">Cari</Button>
                    </div>
                </div>
                <div class="min-w-0">
                    <Label>Kategori</Label>
                    <select v-model="fKategori" class="mt-1 block h-9 w-full min-w-0 truncate rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20 md:w-auto" @change="terapkan()">
                        <option value="">Semua Kategori</option>
                        <option v-for="k in kategori_list" :key="k.id_kategori" :value="k.id_kategori">{{ k.nama }}</option>
                    </select>
                </div>
                <div class="min-w-0">
                    <Label>Kondisi</Label>
                    <select v-model="fKondisi" class="mt-1 block h-9 w-full min-w-0 truncate rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20 md:w-auto" @change="terapkan()">
                        <option value="">Semua</option>
                        <option value="menipis">Menipis</option>
                        <option value="habis">Habis</option>
                    </select>
                </div>
            </div>

            <div v-if="loading" class="space-y-2 py-4">
                <Skeleton v-for="i in 3" :key="i" class="h-12 w-full" />
            </div>
            <div v-else-if="rows.length === 0" class="mt-3 rounded-lg bg-neutral-50 px-4 py-8 text-center text-sm text-neutral-400">
                Tidak ada produk yang cocok.
            </div>
            <div v-else class="mt-3">
                <!-- Kartu mobile -->
                <div class="space-y-2 sm:hidden">
                    <div
                        v-for="r in rows"
                        :key="r.id_barang"
                        class="rounded-lg border border-neutral-100 border-l-4 px-3 py-2.5 text-sm"
                        :class="kondisiStok(r.stok) === 'habis' ? 'border-l-red-500' : kondisiStok(r.stok) === 'menipis' ? 'border-l-amber-500' : 'border-l-emerald-500'"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="truncate font-semibold text-neutral-900">{{ r.nama }}</p>
                            <span class="shrink-0 font-bold whitespace-nowrap" :class="r.stok <= 0 ? 'text-red-600' : r.stok <= batas_menipis ? 'text-amber-600' : 'text-neutral-700'">{{ r.stok }} {{ r.satuan ?? '' }}</span>
                        </div>
                        <p class="mt-1 flex items-center justify-between gap-2 text-xs text-neutral-400">
                            <span class="truncate">{{ r.kategori?.nama ?? '-' }}</span>
                            <span class="shrink-0 whitespace-nowrap"><span class="text-green-600">+{{ r.total_masuk }}</span> · <span class="text-red-500">−{{ r.total_keluar }}</span></span>
                        </p>
                    </div>
                </div>
                <div class="hidden overflow-x-auto sm:block">
                <table class="w-full min-w-180 text-left text-sm">
                    <thead>
                        <tr class="border-b border-neutral-100 text-xs text-neutral-400">
                            <th class="py-2 pr-2 font-medium">Produk</th>
                            <th class="py-2 pr-2 font-medium">Kategori</th>
                            <th class="py-2 pr-2 text-right font-medium">Masuk</th>
                            <th class="py-2 pr-2 text-right font-medium">Keluar</th>
                            <th class="py-2 pr-2 text-center font-medium">Stok</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in rows" :key="r.id_barang" class="border-b border-neutral-50 last:border-0 hover:bg-neutral-50">
                            <td class="py-2 pr-2">
                                <p class="font-medium text-neutral-900">{{ r.nama }}</p>
                                <p v-if="r.barcode" class="font-mono text-[11px] text-neutral-400">{{ r.barcode }}</p>
                            </td>
                            <td class="py-2 pr-2 text-neutral-600">{{ r.kategori?.nama ?? '-' }}</td>
                            <td class="py-2 pr-2 text-right font-semibold text-green-600">+{{ r.total_masuk }}</td>
                            <td class="py-2 pr-2 text-right font-semibold text-red-500">−{{ r.total_keluar }}</td>
                            <td class="py-2 pr-2 text-center font-bold" :class="r.stok <= 0 ? 'text-red-600' : r.stok <= batas_menipis ? 'text-amber-600' : 'text-neutral-700'">{{ r.stok }} <span class="font-normal text-neutral-400">{{ r.satuan ?? '' }}</span></td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <div class="mt-3 flex items-center justify-between text-sm text-neutral-500">
                    <span>Total {{ totalBaris }} produk</span>
                    <div class="flex items-center gap-2">
                        <button type="button" class="flex h-10 min-w-10 items-center justify-center rounded-md border border-neutral-200 px-3 disabled:opacity-40" :disabled="curPage <= 1" @click="curPage--; muat()">‹</button>
                        <span>{{ curPage }} / {{ lastPage }}</span>
                        <button type="button" class="flex h-10 min-w-10 items-center justify-center rounded-md border border-neutral-200 px-3 disabled:opacity-40" :disabled="curPage >= lastPage" @click="curPage++; muat()">›</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
