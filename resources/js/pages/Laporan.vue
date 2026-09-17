<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Banknote, Download, ReceiptText, Scale } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Laporan', href: '/laporan' }],
    },
});

type SekolahOpt = { id_sekolah: number; nama_sekolah: string | null };

const props = defineProps<{
    sekolah?: { id_sekolah: number; nama_sekolah: string | null } | null;
    sekolah_list: SekolahOpt[];
    is_super_admin: boolean;
    default_mulai: string;
    default_akhir: string;
}>();

type Row = {
    id_penjualan: number;
    tanggal_penjualan: string | null;
    total_faktur: number;
    total_bayar: number;
    status_pembayaran: string | null;
    jenis_transaksi: string | null;
    kasir?: { nama_lengkap: string | null; username: string | null } | null;
    sekolah?: { nama_sekolah: string | null } | null;
};

const fMulai = ref(props.default_mulai);
const fAkhir = ref(props.default_akhir);
const fSekolah = ref('');
const fStatus = ref('');
const ringkasan = ref({ omzet: 0, transaksi: 0, rata_rata: 0 });
const rows = ref<Row[]>([]);
const curPage = ref(1);
const lastPage = ref(1);
const totalBaris = ref(0);
const loading = ref(false);
const mengekspor = ref(false);

async function muat() {
    loading.value = true;
    try {
        const q = new URLSearchParams({
            tgl_mulai: fMulai.value, tgl_akhir: fAkhir.value,
            id_sekolah: fSekolah.value, status: fStatus.value,
            page: String(curPage.value),
        });
        const r = await fetch(`/laporan/data?${q}`, {
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

function exportCsv() {
    mengekspor.value = true;
    const q = new URLSearchParams({
        tgl_mulai: fMulai.value, tgl_akhir: fAkhir.value,
        id_sekolah: fSekolah.value, status: fStatus.value,
    });
    window.location.href = `/laporan/export?${q}`;
    // Unduhan via navigasi tidak memberi callback selesai; lepas kunci setelah jeda.
    setTimeout(() => { mengekspor.value = false; }, 3000);
}

onMounted(() => muat());

const formatRp = (n: number) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');
const formatTgl = (s: string | null) => {
    if (!s) return '-';
    const d = new Date(s.replace(' ', 'T'));
    return isNaN(d.getTime()) ? s : d.toLocaleString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const cards = computed(() => [
    { title: 'Total Omzet', value: formatRp(ringkasan.value.omzet), icon: Banknote, card: 'border-emerald-100 bg-emerald-50', chip: 'bg-emerald-500 text-white' },
    { title: 'Total Transaksi', value: String(ringkasan.value.transaksi), icon: ReceiptText, card: 'border-sky-100 bg-sky-50', chip: 'bg-sky-500 text-white' },
    { title: 'Rata-rata / Transaksi', value: formatRp(ringkasan.value.rata_rata), icon: Scale, card: 'border-violet-100 bg-violet-50', chip: 'bg-violet-500 text-white' },
]);
</script>

<template>
    <Head title="Laporan" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-emerald-800">Laporan Penjualan</h1>
                <p class="mt-0.5 text-sm text-neutral-500">
                    Rekap transaksi per periode
                    <span v-if="sekolah?.nama_sekolah && !is_super_admin" class="font-medium text-neutral-700"> — {{ sekolah.nama_sekolah }}</span>
                </p>
            </div>
            <Button type="button" variant="outline" class="h-11" :disabled="mengekspor" @click="exportCsv()">
                <Download class="mr-1 h-4 w-4" /> {{ mengekspor ? 'Menyiapkan...' : 'Export CSV' }}
            </Button>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
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
                <div class="min-w-0">
                    <Label>Tanggal Mulai</Label>
                    <Input v-model="fMulai" type="date" class="mt-1 w-full min-w-0 [&::-webkit-calendar-picker-indicator]:cursor-pointer [&::-webkit-calendar-picker-indicator]:opacity-70" @change="terapkan()" />
                </div>
                <div class="min-w-0">
                    <Label>Tanggal Akhir</Label>
                    <Input v-model="fAkhir" type="date" class="mt-1 w-full min-w-0 [&::-webkit-calendar-picker-indicator]:cursor-pointer [&::-webkit-calendar-picker-indicator]:opacity-70" @change="terapkan()" />
                </div>
                <div v-if="is_super_admin" class="min-w-0">
                    <Label>Sekolah</Label>
                    <select v-model="fSekolah" class="mt-1 block h-9 w-full min-w-0 truncate rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20" @change="terapkan()">
                        <option value="">Semua Sekolah</option>
                        <option v-for="s in sekolah_list" :key="s.id_sekolah" :value="s.id_sekolah">{{ s.nama_sekolah }}</option>
                    </select>
                </div>
                <div class="min-w-0">
                    <Label>Status</Label>
                    <select v-model="fStatus" class="mt-1 block h-9 w-full min-w-0 truncate rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20" @change="terapkan()">
                        <option value="">Semua</option>
                        <option value="sudah bayar">Sudah bayar</option>
                        <option value="belum bayar">Belum bayar</option>
                    </select>
                </div>
                <Button type="button" class="w-full bg-emerald-600 hover:bg-emerald-700 sm:col-span-2 md:col-span-1 md:w-auto" @click="terapkan()">Tampilkan</Button>
            </div>

            <div v-if="loading" class="py-8 text-center text-sm text-neutral-400">Memuat...</div>
            <div v-else-if="rows.length === 0" class="mt-3 rounded-lg bg-neutral-50 px-4 py-8 text-center text-sm text-neutral-400">
                Tidak ada transaksi pada periode ini.
            </div>
            <div v-else class="mt-3">
                <!-- Kartu mobile -->
                <div class="space-y-2 sm:hidden">
                    <div v-for="(r, i) in rows" :key="r.id_penjualan" class="rounded-lg border border-neutral-100 px-3 py-2.5 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-bold whitespace-nowrap text-neutral-900">{{ formatRp(r.total_faktur) }}</span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="r.status_pembayaran === 'sudah bayar' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'">
                                {{ r.status_pembayaran }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs whitespace-nowrap text-neutral-500">{{ formatTgl(r.tanggal_penjualan) }} · {{ r.kasir?.nama_lengkap ?? r.kasir?.username ?? '-' }}</p>
                        <p v-if="is_super_admin" class="mt-0.5 truncate text-xs text-neutral-400">{{ r.sekolah?.nama_sekolah ?? '-' }}</p>
                    </div>
                </div>
                <div class="hidden overflow-x-auto sm:block">
                <table class="w-full min-w-190 text-left text-sm">
                    <thead>
                        <tr class="border-b border-neutral-100 text-xs text-neutral-400">
                            <th class="py-2 pr-2 font-medium">No</th>
                            <th class="py-2 pr-2 font-medium">Tanggal</th>
                            <th v-if="is_super_admin" class="py-2 pr-2 font-medium">Sekolah</th>
                            <th class="py-2 pr-2 font-medium">Kasir</th>
                            <th class="py-2 pr-2 text-right font-medium">Total</th>
                            <th class="py-2 text-center font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, i) in rows" :key="r.id_penjualan" class="border-b border-neutral-50 last:border-0 hover:bg-neutral-50">
                            <td class="py-2 pr-2 text-neutral-500">{{ (curPage - 1) * 10 + i + 1 }}</td>
                            <td class="py-2 pr-2 whitespace-nowrap text-neutral-700">{{ formatTgl(r.tanggal_penjualan) }}</td>
                            <td v-if="is_super_admin" class="max-w-44 truncate py-2 pr-2 text-neutral-600">{{ r.sekolah?.nama_sekolah ?? '-' }}</td>
                            <td class="max-w-36 truncate py-2 pr-2 text-neutral-600">{{ r.kasir?.nama_lengkap ?? r.kasir?.username ?? '-' }}</td>
                            <td class="py-2 pr-2 text-right font-semibold whitespace-nowrap">{{ formatRp(r.total_faktur) }}</td>
                            <td class="py-2 text-center">
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="r.status_pembayaran === 'sudah bayar' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'">
                                    {{ r.status_pembayaran }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <div class="mt-3 flex items-center justify-between text-sm text-neutral-500">
                    <span>Total {{ totalBaris }} transaksi</span>
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
