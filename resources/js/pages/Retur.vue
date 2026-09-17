<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { RotateCcw, Search, ShoppingBag, ShoppingCart, X } from '@lucide/vue';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Retur', href: '/retur' }],
    },
});

const props = defineProps<{
    sekolah?: { id_sekolah: number; nama_sekolah: string | null } | null;
    is_super_admin: boolean;
}>();

const page = usePage();
const flash = computed(() => page.props.flash as Record<string, unknown>);
const formErrors = computed(() => (page.props.errors ?? {}) as Record<string, string>);
const successMsg = computed(() => flash.value.success as string | undefined);

type ItemSisa = { id_barang: number; nama: string | null; qty_beli: number; harga: number; sudah_retur: number; sisa: number };
type FakturJual = { id_penjualan: number; tanggal: string | null; items: ItemSisa[] };
type FakturBeli = { id_pembelian: number; nomor_faktur: string | null; tanggal: string | null; items: ItemSisa[] };

const tab = ref<'jual' | 'beli'>('jual');

// ---------- Form retur (dipakai kedua tab) ----------
const keyword = ref('');
const hasil = ref<Array<FakturJual | FakturBeli>>([]);
const loading = ref(false);
const dipilih = ref<FakturJual | FakturBeli | null>(null);
const qty = reactive<Record<number, number>>({});
const alasan = ref('');
let timer: ReturnType<typeof setTimeout> | undefined;

watch(keyword, () => {
    clearTimeout(timer);
    timer = setTimeout(cari, 300);
});
watch(tab, () => {
    keyword.value = '';
    hasil.value = [];
    dipilih.value = null;
    for (const k of Object.keys(qty)) delete qty[Number(k)];
    alasan.value = '';
});

async function cari() {
    loading.value = true;
    try {
        const url = tab.value === 'jual' ? '/retur/penjualan-cari' : '/retur/pembelian-cari';
        const r = await fetch(`${url}?search=${encodeURIComponent(keyword.value)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const j = await r.json();
        hasil.value = j.data ?? [];
    } catch {
        hasil.value = [];
    } finally {
        loading.value = false;
    }
}

function pilih(f: FakturJual | FakturBeli) {
    dipilih.value = f;
    for (const k of Object.keys(qty)) delete qty[Number(k)];
    for (const it of f.items) if (it.sisa > 0) qty[it.id_barang] = 0;
    keyword.value = '';
    hasil.value = [];
}

function labelFaktur(f: FakturJual | FakturBeli): string {
    return 'id_penjualan' in f ? `#${f.id_penjualan}` : (f.nomor_faktur ?? `#${f.id_pembelian}`);
}

function simpan() {
    if (!dipilih.value) return;
    const items = dipilih.value.items
        .filter((it) => (qty[it.id_barang] || 0) > 0)
        .map((it) => ({ id_barang: it.id_barang, jumlah: Number(qty[it.id_barang]) }));
    if (items.length === 0) return;

    const isJual = tab.value === 'jual';
    router.post(isJual ? '/retur/penjualan' : '/retur/pembelian', {
        ...(isJual
            ? { id_penjualan: (dipilih.value as FakturJual).id_penjualan }
            : { id_pembelian: (dipilih.value as FakturBeli).id_pembelian }),
        alasan: alasan.value || null,
        items,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            dipilih.value = null;
            alasan.value = '';
            muatRiwayat();
        },
    });
}

// ---------- Riwayat ----------
type RiwayatRow = { id_retur: number; tipe: string; id_referensi: number; jumlah: number; harga: number; subtotal: number; alasan: string | null; tanggal_retur: string | null; barang?: { nama: string | null } | null };
const riwayat = ref<RiwayatRow[]>([]);
const rPage = ref(1);
const rLast = ref(1);
const rTotal = ref(0);
const rLoading = ref(false);

async function muatRiwayat() {
    rLoading.value = true;
    try {
        const r = await fetch(`/retur/data?tipe=${tab.value === 'jual' ? 'penjualan' : 'pembelian'}&page=${rPage.value}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const j = await r.json();
        riwayat.value = j.data;
        rPage.value = j.current_page;
        rLast.value = j.last_page;
        rTotal.value = j.total;
    } catch { /* abaikan */ } finally { rLoading.value = false; }
}

watch(tab, () => { rPage.value = 1; muatRiwayat(); });
onMounted(() => { cari(); muatRiwayat(); });

const formatRp = (n: number) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');
const formatTgl = (s: string | null) => {
    if (!s) return '-';
    const d = new Date(s.replace(' ', 'T'));
    return isNaN(d.getTime()) ? s : d.toLocaleString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <Head title="Retur" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-emerald-800">Retur</h1>
            <p class="mt-0.5 text-sm text-neutral-500">
                Retur penjualan menambah stok, retur pembelian mengurangi stok. Total faktur asli tidak diubah.
                <span v-if="sekolah?.nama_sekolah && !is_super_admin" class="font-medium text-neutral-700"> — {{ sekolah.nama_sekolah }}</span>
            </p>
        </div>

        <div class="grid w-full grid-cols-2 gap-1 rounded-lg bg-neutral-100 p-1 sm:w-fit">
            <button type="button" class="flex min-h-11 items-center justify-center gap-1.5 rounded-md px-2 py-2 text-center text-xs font-medium transition sm:px-3 sm:py-1.5 sm:text-sm" :class="tab === 'jual' ? 'bg-emerald-600 text-white shadow-sm' : 'text-neutral-500 hover:text-emerald-700'" @click="tab = 'jual'">
                <ShoppingBag class="h-4 w-4 shrink-0" /> <span class="leading-tight">Retur Penjualan</span>
            </button>
            <button type="button" class="flex min-h-11 items-center justify-center gap-1.5 rounded-md px-2 py-2 text-center text-xs font-medium transition sm:px-3 sm:py-1.5 sm:text-sm" :class="tab === 'beli' ? 'bg-emerald-600 text-white shadow-sm' : 'text-neutral-500 hover:text-emerald-700'" @click="tab = 'beli'">
                <ShoppingCart class="h-4 w-4 shrink-0" /> <span class="leading-tight">Retur Pembelian</span>
            </button>
        </div>

        <div v-if="successMsg" class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">{{ successMsg }}</div>
        <div v-if="formErrors.retur" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ formErrors.retur }}</div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <!-- Form -->
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                <h2 class="flex items-center gap-2 text-sm font-bold text-emerald-800">
                    <RotateCcw class="h-4 w-4" /> Buat Retur {{ tab === 'jual' ? 'Penjualan' : 'Pembelian' }}
                </h2>
                <div v-if="!dipilih" class="relative mt-2">
                    <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-emerald-600" />
                    <Input v-model="keyword" :placeholder="tab === 'jual' ? 'Cari ID penjualan...' : 'Cari nomor faktur...'" class="pl-9" autocomplete="off" />
                    <div v-if="loading" class="mt-2 text-xs text-neutral-400">Mencari...</div>
                    <div v-else-if="hasil.length > 0" class="absolute z-10 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-neutral-200 bg-white shadow-lg">
                        <button v-for="f in hasil" :key="labelFaktur(f)" type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-neutral-50" @click="pilih(f)">
                            <span class="block font-medium text-neutral-900">{{ labelFaktur(f) }}</span>
                            <span class="block text-xs text-neutral-400">{{ f.tanggal ?? '-' }} · {{ f.items.length }} item</span>
                        </button>
                    </div>
                    <p v-else class="mt-2 text-xs text-neutral-400">{{ tab === 'jual' ? 'Ketik ID penjualan (faktur jual).' : 'Hanya faktur pembelian berstatus selesai.' }}</p>
                </div>
                <div v-else class="mt-2">
                    <div class="flex items-center justify-between rounded-lg bg-emerald-50 px-3 py-2 text-sm">
                        <span class="font-semibold text-emerald-800">{{ labelFaktur(dipilih) }} · {{ dipilih.tanggal ?? '-' }}</span>
                        <button type="button" class="text-neutral-400 hover:text-emerald-700" @click="dipilih = null"><X class="h-4 w-4" /></button>
                    </div>
                    <div class="mt-3 space-y-2">
                        <div v-for="it in dipilih.items" :key="it.id_barang" class="flex items-center justify-between gap-2 rounded-lg border border-neutral-100 px-3 py-2 text-sm">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-neutral-800">{{ it.nama }}</p>
                                <p class="text-xs text-neutral-400">Beli {{ it.qty_beli }} · diretur {{ it.sudah_retur }} · sisa {{ it.sisa }}</p>
                            </div>
                            <input v-model.number="qty[it.id_barang]" type="number" min="0" :max="it.sisa" :disabled="it.sisa <= 0" class="h-9 w-20 rounded-lg border border-gray-300 bg-gray-50 px-2 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <Label>Alasan (opsional)</Label>
                        <Input v-model="alasan" class="mt-1.5" placeholder="Contoh: barang rusak" />
                    </div>
                    <InputError :message="formErrors.items" />
                    <Button type="button" class="mt-3 w-full bg-emerald-600 hover:bg-emerald-700" @click="simpan()">Simpan Retur</Button>
                </div>
            </div>

            <!-- Riwayat -->
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-bold text-emerald-800">Riwayat Retur {{ tab === 'jual' ? 'Penjualan' : 'Pembelian' }}</h2>
                <div v-if="rLoading" class="py-8 text-center text-sm text-neutral-400">Memuat...</div>
                <div v-else-if="riwayat.length === 0" class="mt-3 rounded-lg bg-neutral-50 px-4 py-8 text-center text-sm text-neutral-400">Belum ada retur.</div>
                <div v-else class="mt-3">
                    <div class="space-y-2 sm:hidden">
                        <div v-for="r in riwayat" :key="r.id_retur" class="rounded-lg border border-neutral-100 px-3 py-2.5 text-sm" :title="r.alasan ?? ''">
                            <div class="flex items-center justify-between gap-2">
                                <p class="truncate font-semibold text-neutral-900">{{ r.barang?.nama ?? '-' }}</p>
                                <span class="shrink-0 font-bold whitespace-nowrap">{{ formatRp(r.subtotal) }}</span>
                            </div>
                            <p class="mt-1 text-xs text-neutral-400">#{{ r.id_referensi }} · {{ formatTgl(r.tanggal_retur) }} · Qty {{ r.jumlah }}</p>
                        </div>
                    </div>
                    <div class="hidden overflow-x-auto sm:block">
                    <table class="w-full min-w-140 text-left text-sm">
                        <thead>
                            <tr class="border-b border-neutral-100 text-xs text-neutral-400">
                                <th class="py-2 pr-2 font-medium">Tanggal</th>
                                <th class="py-2 pr-2 font-medium">Ref</th>
                                <th class="py-2 pr-2 font-medium">Barang</th>
                                <th class="py-2 pr-2 text-right font-medium">Qty</th>
                                <th class="py-2 pr-2 text-right font-medium">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in riwayat" :key="r.id_retur" class="border-b border-neutral-50 last:border-0" :title="r.alasan ?? ''">
                                <td class="py-2 pr-2 whitespace-nowrap text-neutral-500">{{ formatTgl(r.tanggal_retur) }}</td>
                                <td class="py-2 pr-2 text-neutral-600">#{{ r.id_referensi }}</td>
                                <td class="max-w-36 truncate py-2 pr-2 text-neutral-700">{{ r.barang?.nama ?? '-' }}</td>
                                <td class="py-2 pr-2 text-right font-semibold">{{ r.jumlah }}</td>
                                <td class="py-2 text-right whitespace-nowrap">{{ formatRp(r.subtotal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                    <div class="mt-3 flex items-center justify-between text-sm text-neutral-500">
                        <span>Total {{ rTotal }} retur</span>
                        <div class="flex items-center gap-2">
                            <button type="button" class="flex h-10 min-w-10 items-center justify-center rounded-md border border-neutral-200 px-3 disabled:opacity-40" :disabled="rPage <= 1" @click="rPage--; muatRiwayat()">‹</button>
                            <span>{{ rPage }} / {{ rLast }}</span>
                            <button type="button" class="flex h-10 min-w-10 items-center justify-center rounded-md border border-neutral-200 px-3 disabled:opacity-40" :disabled="rPage >= rLast" @click="rPage++; muatRiwayat()">›</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
