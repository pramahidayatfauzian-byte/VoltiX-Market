<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Search, X } from '@lucide/vue';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { konfirmasi } from '@/composables/useConfirm';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pembelian', href: '/pembelian' }],
    },
});

type Supplier = { id_supplier: number; nama: string | null; no_telepon?: string | null; alamat_supplier?: string | null };
type BarangOpt = { id_barang: number; barcode: string | null; nama: string | null; satuan: string | null; harga_beli: number; stok: number };
type SekolahOpt = { id_sekolah: number; nama_sekolah: string | null };

const props = defineProps<{
    sekolah?: { id_sekolah: number; nama_sekolah: string | null } | null;
    supplier_list: Supplier[];
    barang_list: BarangOpt[];
    sekolah_list: SekolahOpt[];
    is_super_admin: boolean;
}>();

const page = usePage();
const flash = computed(() => page.props.flash as Record<string, unknown>);
const formErrors = computed(() => (page.props.errors ?? {}) as Record<string, string>);
const successMsg = computed(() => flash.value.success as string | undefined);

type Paginate<T> = { data: T[]; current_page: number; last_page: number; total: number };
type BeliRow = {
    id_pembelian: number; nomor_faktur: string | null; tanggal_faktur: string | null;
    total_bayar: number; status_pembelian: string | null; jenis_transaksi: string | null;
    cara_bayar: string | null; note: string | null;
    supplier?: { nama: string | null } | null;
    user?: { nama_lengkap: string | null; username: string | null } | null;
};

// ---------- PEMBELIAN ----------
const bSearch = ref('');
const bSupplier = ref('');
const bMulai = ref('');
const bAkhir = ref('');
const bStatus = ref('');
const bList = ref<Paginate<BeliRow>>({ data: [], current_page: 1, last_page: 1, total: 0 });
const bPage = ref(1);
const bLoading = ref(false);
let bTimer: ReturnType<typeof setTimeout> | undefined;
watch(bSearch, () => { clearTimeout(bTimer); bTimer = setTimeout(() => { bPage.value = 1; muatBeli(); }, 400); });

async function muatBeli() {
    bLoading.value = true;
    try {
        const q = new URLSearchParams({
            search: bSearch.value, page: String(bPage.value),
            id_supplier: bSupplier.value, tgl_mulai: bMulai.value,
            tgl_akhir: bAkhir.value, status: bStatus.value,
        });
        const r = await fetch(`/pembelian/data?${q}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        bList.value = await r.json();
    } catch { /* abaikan */ } finally { bLoading.value = false; }
}
function terapkanFilterBeli() { bPage.value = 1; muatBeli(); }

type ItemForm = { id_barang: number | ''; jumlah: number; harga_beli: number };
const showForm = ref(false);
const f = reactive({
    id_supplier: '' as string | number,
    nomor_faktur: '',
    tanggal_faktur: new Date().toISOString().slice(0, 16),
    status_pembelian: 'draft',
    jenis_transaksi: 'tunai',
    cara_bayar: 'cash',
    note: '',
    items: [{ id_barang: '', jumlah: 1, harga_beli: 0 }] as ItemForm[],
});
const totalForm = computed(() => f.items.reduce((s, i) => s + (Number(i.jumlah) || 0) * (Number(i.harga_beli) || 0), 0));

function tambahBaris() { f.items.push({ id_barang: '', jumlah: 1, harga_beli: 0 }); }
function hapusBaris(i: number) { f.items.splice(i, 1); }
function isiHarga(i: number) {
    const b = props.barang_list.find((x) => x.id_barang === Number(f.items[i].id_barang));
    if (b) f.items[i].harga_beli = Number(b.harga_beli) || 0;
}
function bukaTambah() {
    Object.assign(f, {
        id_supplier: '', nomor_faktur: '', tanggal_faktur: new Date().toISOString().slice(0, 16),
        status_pembelian: 'draft', jenis_transaksi: 'tunai', cara_bayar: 'cash', note: '',
        items: [{ id_barang: '', jumlah: 1, harga_beli: 0 }],
    });
    showForm.value = true;
}
function simpanBeli() {
    router.post('/pembelian', {
        id_supplier: Number(f.id_supplier),
        nomor_faktur: f.nomor_faktur,
        tanggal_faktur: f.tanggal_faktur.replace('T', ' ') + ':00',
        status_pembelian: f.status_pembelian,
        jenis_transaksi: f.jenis_transaksi,
        cara_bayar: f.cara_bayar,
        note: f.note || null,
        items: f.items.map((i) => ({ id_barang: Number(i.id_barang), jumlah: Number(i.jumlah), harga_beli: Number(i.harga_beli) })),
    }, { preserveScroll: true, onSuccess: () => { showForm.value = false; muatBeli(); } });
}
async function selesaikan(id: number) {
    if (!(await konfirmasi({ pesan: 'Selesaikan faktur ini? Stok akan bertambah.', varian: 'utama', teksYa: 'Ya, selesaikan' }))) return;
    router.post(`/pembelian/${id}/selesaikan`, {}, { preserveScroll: true, onSuccess: () => muatBeli() });
}
async function hapusBeli(id: number) {
    if (!(await konfirmasi('Hapus faktur draft ini?'))) return;
    router.delete(`/pembelian/${id}`, { preserveScroll: true, onSuccess: () => muatBeli() });
}

// Detail
const showDetail = ref(false);
type DetailData = BeliRow & { detail: Array<{ id_barang: number; jumlah: number; harga_beli: number; subtotal: number; barang?: { nama: string | null } | null }> };
const detailData = ref<DetailData | null>(null);
async function bukaDetail(id: number) {
    try {
        const r = await fetch(`/pembelian/${id}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const j = await r.json();
        detailData.value = j.data;
        showDetail.value = true;
    } catch { /* abaikan */ }
}

onMounted(() => { muatBeli(); });

const formatRp = (n: number) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');
const formatTgl = (s: string | null) => {
    if (!s) return '-';
    const d = new Date(s.replace(' ', 'T'));
    return isNaN(d.getTime()) ? s : d.toLocaleString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <Head title="Pembelian" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-emerald-800">Data Pembelian</h1>
            <p class="mt-0.5 text-sm text-neutral-500">
                Kelola faktur pembelian
                <span v-if="sekolah?.nama_sekolah" class="font-medium text-neutral-700"> — {{ sekolah.nama_sekolah }}</span>
            </p>
        </div>

        <div v-if="successMsg" class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">{{ successMsg }}</div>
        <div v-if="formErrors.faktur" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ formErrors.faktur }}</div>

        <!-- PEMBELIAN -->
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-2 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex flex-1 flex-col gap-2 md:flex-row md:flex-wrap">
                    <div class="relative w-full md:max-w-55">
                        <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-emerald-600" />
                        <Input v-model="bSearch" placeholder="Cari nomor pembelian..." class="pl-9" />
                    </div>
                    <div class="grid grid-cols-2 gap-2 md:flex md:flex-wrap">
                    <input v-model="bMulai" type="date" class="h-9 w-full min-w-0 rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20 md:w-auto [&::-webkit-calendar-picker-indicator]:cursor-pointer [&::-webkit-calendar-picker-indicator]:opacity-70" @change="terapkanFilterBeli()" />
                    <input v-model="bAkhir" type="date" class="h-9 w-full min-w-0 rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20 md:w-auto [&::-webkit-calendar-picker-indicator]:cursor-pointer [&::-webkit-calendar-picker-indicator]:opacity-70" @change="terapkanFilterBeli()" />
                    <select v-model="bSupplier" class="h-9 w-full min-w-0 truncate rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20 md:w-auto" @change="terapkanFilterBeli()">
                        <option value="">Semua Supplier</option>
                        <option v-for="s in supplier_list" :key="s.id_supplier" :value="s.id_supplier">{{ s.nama }}</option>
                    </select>
                    <select v-model="bStatus" class="h-9 w-full min-w-0 truncate rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20 md:w-auto" @change="terapkanFilterBeli()">
                        <option value="">Semua Status</option>
                        <option value="draft">Draft</option>
                        <option value="selesai">Selesai</option>
                    </select>
                    </div>
                </div>
                <Button type="button" class="h-11 w-full bg-emerald-600 hover:bg-emerald-700 xl:w-auto" @click="bukaTambah">+ Tambah Pembelian</Button>
            </div>

            <div v-if="bLoading" class="py-8 text-center text-sm text-neutral-400">Memuat...</div>
            <div v-else-if="bList.data.length === 0" class="mt-3 rounded-lg bg-neutral-50 px-4 py-8 text-center text-sm text-neutral-400">Belum ada pembelian.</div>
            <div v-else class="mt-3">
            <div class="space-y-2 sm:hidden">
                <div v-for="(r, i) in bList.data" :key="r.id_pembelian" class="rounded-lg border border-neutral-100 px-3 py-2.5 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <p class="truncate font-semibold text-neutral-900">{{ r.nomor_faktur }}</p>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold capitalize" :class="r.status_pembelian === 'selesai' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'">{{ r.status_pembelian }}</span>
                    </div>
                    <div class="mt-1 flex items-center justify-between gap-2 text-xs">
                        <span class="truncate text-neutral-400">{{ r.supplier?.nama ?? '-' }} · {{ formatTgl(r.tanggal_faktur) }}</span>
                        <span class="shrink-0 font-bold whitespace-nowrap text-neutral-900">{{ formatRp(r.total_bayar) }}</span>
                    </div>
                    <div class="mt-2 flex gap-1 border-t border-neutral-50 pt-2">
                        <button type="button" class="flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-xs font-medium text-neutral-700 hover:bg-neutral-100" @click="bukaDetail(r.id_pembelian)">Detail</button>
                        <button v-if="r.status_pembelian === 'draft'" type="button" class="flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-xs font-medium text-green-600 hover:bg-green-50" @click="selesaikan(r.id_pembelian)">Selesaikan</button>
                        <button v-if="r.status_pembelian === 'draft'" type="button" class="flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-xs font-medium text-red-600 hover:bg-red-50" @click="hapusBeli(r.id_pembelian)">Hapus</button>
                    </div>
                </div>
            </div>
            <div class="hidden overflow-x-auto sm:block">
                <table class="w-full min-w-200 text-left text-sm">
                    <thead>
                        <tr class="border-b border-neutral-100 text-xs text-neutral-400">
                            <th class="py-2 pr-2 font-medium">No</th>
                            <th class="py-2 pr-2 font-medium">No Faktur</th>
                            <th class="py-2 pr-2 font-medium">Tanggal</th>
                            <th class="py-2 pr-2 font-medium">Supplier</th>
                            <th class="py-2 pr-2 text-right font-medium">Total</th>
                            <th class="py-2 pr-2 text-center font-medium">Status</th>
                            <th class="py-2 text-center font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, i) in bList.data" :key="r.id_pembelian" class="border-b border-neutral-50 last:border-0 hover:bg-neutral-50">
                            <td class="py-2 pr-2 text-neutral-500">{{ (bList.current_page - 1) * 10 + i + 1 }}</td>
                            <td class="py-2 pr-2 font-medium text-neutral-900">{{ r.nomor_faktur }}</td>
                            <td class="py-2 pr-2 whitespace-nowrap text-neutral-600">{{ formatTgl(r.tanggal_faktur) }}</td>
                            <td class="py-2 pr-2 text-neutral-600">{{ r.supplier?.nama ?? '-' }}</td>
                            <td class="py-2 pr-2 text-right font-semibold whitespace-nowrap">{{ formatRp(r.total_bayar) }}</td>
                            <td class="py-2 pr-2 text-center">
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold capitalize" :class="r.status_pembelian === 'selesai' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'">{{ r.status_pembelian }}</span>
                            </td>
                            <td class="py-2 text-center whitespace-nowrap">
                                <button type="button" class="rounded-md px-2 py-1 text-xs font-medium text-neutral-700 hover:bg-neutral-100" @click="bukaDetail(r.id_pembelian)">Detail</button>
                                <button v-if="r.status_pembelian === 'draft'" type="button" class="rounded-md px-2 py-1 text-xs font-medium text-green-600 hover:bg-green-50" @click="selesaikan(r.id_pembelian)">Selesaikan</button>
                                <button v-if="r.status_pembelian === 'draft'" type="button" class="rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50" @click="hapusBeli(r.id_pembelian)">Hapus</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <div class="mt-3 flex items-center justify-between text-sm text-neutral-500">
                    <span>Total {{ bList.total }} faktur</span>
                    <div class="flex items-center gap-2">
                        <button type="button" class="flex h-10 min-w-10 items-center justify-center rounded-md border border-neutral-200 px-3 disabled:opacity-40" :disabled="bPage <= 1" @click="bPage--; muatBeli()">‹</button>
                        <span>{{ bPage }} / {{ bList.last_page }}</span>
                        <button type="button" class="flex h-10 min-w-10 items-center justify-center rounded-md border border-neutral-200 px-3 disabled:opacity-40" :disabled="bPage >= bList.last_page" @click="bPage++; muatBeli()">›</button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Modal tambah pembelian -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 p-4" @click.self="showForm = false">
        <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-5 shadow-xl">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-emerald-800">Tambah Pembelian</h3>
                <button type="button" class="text-neutral-400 hover:text-emerald-700" @click="showForm = false"><X class="h-5 w-5" /></button>
            </div>
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div><Label>Supplier</Label>
                    <select v-model="f.id_supplier" class="mt-1.5 w-full h-9 rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20">
                        <option value="">-- Pilih supplier --</option>
                        <option v-for="s in supplier_list" :key="s.id_supplier" :value="s.id_supplier">{{ s.nama }}</option>
                    </select>
                    <InputError :message="formErrors.id_supplier" />
                </div>
                <div><Label>No Faktur</Label><Input v-model="f.nomor_faktur" class="mt-1.5" placeholder="PB-0001" /><InputError :message="formErrors.nomor_faktur" /></div>
                <div><Label>Tanggal Faktur</Label><input v-model="f.tanggal_faktur" type="datetime-local" class="mt-1.5 w-full h-9 rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20" /></div>
                <div><Label>Status</Label>
                    <select v-model="f.status_pembelian" class="mt-1.5 w-full h-9 rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20">
                        <option value="draft">Draft (stok belum masuk)</option>
                        <option value="selesai">Selesai (stok bertambah)</option>
                    </select>
                </div>
                <div><Label>Jenis Transaksi</Label>
                    <select v-model="f.jenis_transaksi" class="mt-1.5 w-full h-9 rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20">
                        <option value="tunai">Tunai</option><option value="kredit">Kredit</option>
                    </select>
                </div>
                <div><Label>Cara Bayar</Label>
                    <select v-model="f.cara_bayar" class="mt-1.5 w-full h-9 rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20">
                        <option value="cash">Cash</option><option value="transfer">Transfer</option><option value="qris">QRIS</option><option value="debit">Debit</option>
                    </select>
                </div>
                <div class="sm:col-span-2"><Label>Catatan</Label><Input v-model="f.note" class="mt-1.5" placeholder="Opsional" /></div>
            </div>

            <div class="mt-4">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-bold text-emerald-800">Item Barang</h4>
                    <button type="button" class="text-xs font-medium text-neutral-700 hover:underline" @click="tambahBaris()">+ Baris</button>
                </div>
                <div v-for="(it, i) in f.items" :key="i" class="mt-2 grid grid-cols-12 items-end gap-2 rounded-lg bg-neutral-50 p-2">
                    <div class="col-span-12 sm:col-span-5">
                        <Label>Barang</Label>
                        <select v-model="it.id_barang" class="mt-1 w-full rounded-lg border border-gray-300 bg-gray-50 px-2 py-1.5 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20" @change="isiHarga(i)">
                            <option value="">-- Pilih --</option>
                            <option v-for="b in barang_list" :key="b.id_barang" :value="b.id_barang">{{ b.nama }} (stok {{ b.stok }})</option>
                        </select>
                    </div>
                    <div class="col-span-4 sm:col-span-2"><Label>Jumlah</Label><Input v-model.number="it.jumlah" type="number" min="1" class="mt-1" /></div>
                    <div class="col-span-6 sm:col-span-3"><Label>Harga Beli</Label><Input v-model.number="it.harga_beli" type="number" min="0" class="mt-1" /></div>
                    <div class="col-span-2 sm:col-span-2 flex items-center gap-1">
                        <span class="flex-1 text-right text-xs font-semibold">{{ formatRp((it.jumlah || 0) * (it.harga_beli || 0)) }}</span>
                        <button v-if="f.items.length > 1" type="button" class="text-red-500 hover:text-red-700" @click="hapusBaris(i)"><X class="h-4 w-4" /></button>
                    </div>
                </div>
                <InputError :message="formErrors.items" />
                <div class="mt-2 flex justify-between border-t border-neutral-100 pt-2 text-sm font-bold"><span>Total</span><span>{{ formatRp(totalForm) }}</span></div>
            </div>

            <Button type="button" class="mt-4 w-full bg-emerald-600 hover:bg-emerald-700" @click="simpanBeli()">Simpan Pembelian</Button>
        </div>
    </div>

    <!-- Modal detail -->
    <div v-if="showDetail && detailData" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 p-4" @click.self="showDetail = false">
        <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 shadow-xl">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-emerald-800">Detail {{ detailData.nomor_faktur }}</h3>
                <button type="button" class="text-neutral-400 hover:text-emerald-700" @click="showDetail = false"><X class="h-5 w-5" /></button>
            </div>
            <dl class="mt-3 space-y-1.5 text-sm">
                <div class="flex justify-between"><dt class="text-neutral-400">Supplier</dt><dd class="font-medium">{{ detailData.supplier?.nama ?? '-' }}</dd></div>
                <div class="flex justify-between"><dt class="text-neutral-400">Tanggal</dt><dd>{{ formatTgl(detailData.tanggal_faktur) }}</dd></div>
                <div class="flex justify-between"><dt class="text-neutral-400">Status</dt><dd class="capitalize">{{ detailData.status_pembelian }}</dd></div>
                <div class="flex justify-between"><dt class="text-neutral-400">Jenis / Cara</dt><dd class="capitalize">{{ detailData.jenis_transaksi }} / {{ detailData.cara_bayar }}</dd></div>
            </dl>
            <table class="mt-3 w-full text-left text-sm">
                <thead><tr class="border-b border-neutral-100 text-xs text-neutral-400"><th class="py-1.5 font-medium">Barang</th><th class="py-1.5 text-center font-medium">Qty</th><th class="py-1.5 text-right font-medium">Harga</th><th class="py-1.5 text-right font-medium">Subtotal</th></tr></thead>
                <tbody>
                    <tr v-for="(d, i) in detailData.detail" :key="i" class="border-b border-neutral-50 last:border-0">
                        <td class="py-1.5">{{ d.barang?.nama ?? `#${d.id_barang}` }}</td>
                        <td class="py-1.5 text-center">{{ d.jumlah }}</td>
                        <td class="py-1.5 text-right">{{ formatRp(d.harga_beli) }}</td>
                        <td class="py-1.5 text-right font-semibold">{{ formatRp(d.subtotal) }}</td>
                    </tr>
                </tbody>
            </table>
            <div class="mt-2 flex justify-between text-sm font-bold"><span>Total</span><span>{{ formatRp(detailData.total_bayar) }}</span></div>
        </div>
    </div>

</template>
