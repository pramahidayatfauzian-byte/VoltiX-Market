<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Barcode, FolderTree, Layers, ScanBarcode, Search, ShoppingBag, X } from '@lucide/vue';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import BarcodeView from '@/components/BarcodeView.vue';
import BarcodeScanner from '@/components/BarcodeScanner.vue';
import InputError from '@/components/InputError.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { konfirmasi } from '@/composables/useConfirm';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Produk', href: '/produk' }],
    },
});

type Kategori = { id_kategori: number; nama: string | null; id_kelompok: number | null };
type Kelompok = { id: number; id_sekolah: number | null; nama_kelompok: string | null; sekolah?: { nama_sekolah: string | null } | null };
type Supplier = { id_supplier: number; nama: string | null };
type SekolahOpt = { id_sekolah: number; nama_sekolah: string | null };

const props = defineProps<{
    sekolah?: { id_sekolah: number; nama_sekolah: string | null } | null;
    kategori_list: Kategori[];
    kelompok_list: Kelompok[];
    supplier_list: Supplier[];
    sekolah_list: SekolahOpt[];
    is_super_admin: boolean;
    can_manage?: boolean;
}>();

const page = usePage();
const flash = computed(() => page.props.flash as Record<string, unknown>);
const formErrors = computed(() => (page.props.errors ?? {}) as Record<string, string>);
const successMsg = computed(() => flash.value.success as string | undefined);

type Paginate<T> = { data: T[]; current_page: number; last_page: number; total: number };
type BarangRow = {
    id_barang: number; barcode: string | null; foto: string | null; nama: string | null;
    harga_jual: number; harga_beli: number; stok: number; is_active: boolean;
    satuan: string | null; id_kategori: number | null;
    id_kelompok_kategori: number | null; id_supplier: number | null; id_sekolah: number | null;
    kategori?: { nama: string | null } | null;
    kelompok_kategori?: { nama_kelompok: string | null } | null;
    supplier?: { nama: string | null } | null;
    sekolah?: { nama_sekolah: string | null } | null;
};

const tab = ref<'produk' | 'kategori' | 'kelompok'>('produk');

// ---------- TAB PRODUK ----------
const pSearch = ref('');
const pKategori = ref('');
const pStatus = ref('semua');
const pList = ref<Paginate<BarangRow>>({ data: [], current_page: 1, last_page: 1, total: 0 });
const pPage = ref(1);
const pLoading = ref(false);
let pTimer: ReturnType<typeof setTimeout> | undefined;

watch([pSearch], () => {
    clearTimeout(pTimer);
    pTimer = setTimeout(() => { pPage.value = 1; muatProduk(); }, 400);
});

async function muatProduk() {
    pLoading.value = true;
    try {
        const q = new URLSearchParams({
            search: pSearch.value, page: String(pPage.value),
            id_kategori: pKategori.value, status: pStatus.value,
        });
        const r = await fetch(`/produk/data?${q}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        pList.value = await r.json();
    } catch { /* abaikan */ } finally { pLoading.value = false; }
}

const showForm = ref(false);
const editRow = ref<BarangRow | null>(null);
const showDetail = ref(false);
const detailRow = ref<BarangRow | null>(null);
const f = reactive({
    id_sekolah: '' as string | number, barcode: '', nama: '',
    id_kategori: '' as string | number, id_kelompok_kategori: '' as string | number,
    id_supplier: '' as string | number, satuan: 'pcs',
    harga_beli: 0, harga_jual: 0, stok: 0, is_active: true,
});

function bukaTambah() {
    editRow.value = null;
    Object.assign(f, { id_sekolah: '', barcode: '', nama: '', id_kategori: '', id_kelompok_kategori: '', id_supplier: '', satuan: 'pcs', harga_beli: 0, harga_jual: 0, stok: 0, is_active: true });
    fotoFile.value = null;
    fotoPreview.value = null;
    infoScan.value = '';
    showForm.value = true;
}
function bukaEdit(r: BarangRow) {
    editRow.value = r;
    Object.assign(f, {
        id_sekolah: r.id_sekolah ?? '', barcode: r.barcode ?? '', nama: r.nama ?? '',
        id_kategori: r.id_kategori ?? '', id_kelompok_kategori: r.id_kelompok_kategori ?? '',
        id_supplier: r.id_supplier ?? '', satuan: r.satuan ?? 'pcs',
        harga_beli: Number(r.harga_beli) || 0, harga_jual: Number(r.harga_jual) || 0,
        stok: r.stok ?? 0, is_active: !!r.is_active,
    });
    fotoFile.value = null;
    fotoPreview.value = fotoUrl(r.foto);
    infoScan.value = '';
    showForm.value = true;
}

// ---------- Scan barcode untuk tambah produk ----------
// Alur: pindai -> lookup (/produk/lookup-barcode) -> sudah terdaftar? buka
// edit : prefill form tambah (nama + kategori + satuan + harga bila dikenal).
const showScan = ref(false);
const infoScan = ref('');
const scanLookup = ref(false);

type KandidatProduk = {
    barcode: string;
    nama: string | null;
    kategori: string;
    satuan: string;
    harga: number | null;
    sumber: string | null;
    barcode_valid: boolean;
    produk_indonesia: boolean;
};

function bukaScanProduk(formBaru: boolean) {
    if (formBaru) {
        editRow.value = null;
        Object.assign(f, { id_sekolah: '', barcode: '', nama: '', id_kategori: '', id_kelompok_kategori: '', id_supplier: '', satuan: 'pcs', harga_beli: 0, harga_jual: 0, stok: 0, is_active: true });
        fotoFile.value = null;
        fotoPreview.value = null;
        infoScan.value = '';
        showForm.value = true;
    }
    showScan.value = true;
}

function cocokkanKategori(namaKategori: string) {
    const target = (namaKategori || '').toLowerCase();
    if (!target) return;
    const cocok = props.kategori_list.find((k) => {
        const nama = (k.nama || '').toLowerCase();
        return nama && (nama.includes(target) || target.includes(nama));
    });
    if (cocok) f.id_kategori = cocok.id_kategori;
}

async function onScanProduk(kode: string) {
    showScan.value = false;
    const barcode = kode.trim().replace(/\s+/g, '');
    if (!barcode) return;
    scanLookup.value = true;
    const toastKenali = toast.loading('Mengenali barcode...');
    try {
        const r = await fetch(`/produk/lookup-barcode?barcode=${encodeURIComponent(barcode)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const j = await r.json();
        toast.dismiss(toastKenali);
        if (j.produk) {
            // Sudah terdaftar di database: buka form edit.
            bukaEdit(j.produk as BarangRow);
            infoScan.value = 'Barcode sudah terdaftar — menampilkan data yang ada.';
            toast.info('Barcode sudah terdaftar di database.');
            return;
        }
        const k: KandidatProduk | null = j.kandidat ?? null;
        f.barcode = barcode;
        if (k?.nama) {
            f.nama = k.nama;
            f.satuan = k.satuan || 'pcs';
            cocokkanKategori(k.kategori);
            if (k.harga) {
                f.harga_jual = k.harga;
                f.harga_beli = Math.round(k.harga * 0.8);
            }
            infoScan.value = k.sumber === 'master'
                ? `Dikenali dari katalog Indonesia: ${k.nama} (${k.kategori}).`
                : `Dikenali dari katalog publik: ${k.nama} (${k.kategori}) — periksa harga.`;
            toast.success(`Dikenali: ${k.nama}`);
        } else {
            infoScan.value = 'Barcode belum dikenal — lengkapi nama, kategori, dan harga.';
            toast.info('Barcode belum dikenal, silakan lengkapi datanya.');
        }
    } catch {
        toast.dismiss(toastKenali);
        f.barcode = barcode;
        infoScan.value = 'Gagal mengenali barcode — lengkapi data manual.';
    } finally {
        scanLookup.value = false;
    }
}
async function bukaDetail(r: BarangRow) {
    try {
        const res = await fetch(`/produk/${r.id_barang}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const j = await res.json();
        detailRow.value = j.data;
    } catch { detailRow.value = r; }
    showDetail.value = true;
}
const fotoFile = ref<File | null>(null);
const fotoPreview = ref<string | null>(null);

function pilihFoto(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    fotoFile.value = file;
    fotoPreview.value = file ? URL.createObjectURL(file) : null;
}

function payloadFormData() {
    const num = (v: string | number) => (v === '' ? null : Number(v));
    const fd = new FormData();
    if (props.is_super_admin && f.id_sekolah !== '') fd.append('id_sekolah', String(Number(f.id_sekolah)));
    if (f.barcode) fd.append('barcode', f.barcode);
    fd.append('nama', f.nama);
    const kat = num(f.id_kategori);
    const kel = num(f.id_kelompok_kategori);
    const sup = num(f.id_supplier);
    if (kat !== null) fd.append('id_kategori', String(kat));
    if (kel !== null) fd.append('id_kelompok_kategori', String(kel));
    if (sup !== null) fd.append('id_supplier', String(sup));
    if (f.satuan) fd.append('satuan', f.satuan);
    fd.append('harga_beli', String(Number(f.harga_beli) || 0));
    fd.append('harga_jual', String(Number(f.harga_jual) || 0));
    fd.append('stok', String(Number(f.stok) || 0));
    fd.append('is_active', f.is_active ? '1' : '0');
    if (fotoFile.value) fd.append('foto', fotoFile.value);
    return fd;
}

const fotoUrl = (p: string | null | undefined): string | null => (p ? `/storage/${p}` : null);

function simpanProduk() {
    const fd = payloadFormData();
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            fotoFile.value = null;
            fotoPreview.value = null;
            muatProduk();
        },
    };
    if (editRow.value) {
        fd.append('_method', 'put');
        router.post(`/produk/${editRow.value.id_barang}`, fd, opts);
    } else {
        router.post('/produk', fd, opts);
    }
}
async function hapusProduk(id: number) {
    if (!(await konfirmasi('Hapus produk ini?'))) return;
    router.delete(`/produk/${id}`, { preserveScroll: true, onSuccess: () => muatProduk() });
}
function toggleProduk(id: number) {
    router.patch(`/produk/${id}/toggle`, {}, { preserveScroll: true, onSuccess: () => muatProduk() });
}

// ---------- TAB KATEGORI ----------
type KatRow = { id_kategori: number; nama: string | null; id_kelompok: number | null; kelompok?: { nama_kelompok: string | null } | null };
const kSearch = ref('');
const kList = ref<Paginate<KatRow>>({ data: [], current_page: 1, last_page: 1, total: 0 });
const kPage = ref(1);
const kLoading = ref(false);
let kTimer: ReturnType<typeof setTimeout> | undefined;
watch(kSearch, () => { clearTimeout(kTimer); kTimer = setTimeout(() => { kPage.value = 1; muatKategori(); }, 400); });
async function muatKategori() {
    kLoading.value = true;
    try {
        const r = await fetch(`/kategori/data?search=${encodeURIComponent(kSearch.value)}&page=${kPage.value}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        kList.value = await r.json();
    } catch { /* abaikan */ } finally { kLoading.value = false; }
}
const showKatForm = ref(false);
const katEdit = ref<KatRow | null>(null);
const katForm = reactive({ id_kelompok: '' as string | number, nama: '' });
function bukaTambahKat() { katEdit.value = null; katForm.id_kelompok = ''; katForm.nama = ''; showKatForm.value = true; }
function bukaEditKat(r: KatRow) { katEdit.value = r; katForm.id_kelompok = r.id_kelompok ?? ''; katForm.nama = r.nama ?? ''; showKatForm.value = true; }
function simpanKat() {
    const payload = { id_kelompok: katForm.id_kelompok === '' ? null : Number(katForm.id_kelompok), nama: katForm.nama };
    if (katEdit.value) router.put(`/kategori/${katEdit.value.id_kategori}`, payload, { preserveScroll: true, onSuccess: () => { showKatForm.value = false; muatKategori(); } });
    else router.post('/kategori', payload, { preserveScroll: true, onSuccess: () => { showKatForm.value = false; muatKategori(); } });
}
async function hapusKat(id: number) {
    if (!(await konfirmasi('Hapus kategori ini?'))) return;
    router.delete(`/kategori/${id}`, { preserveScroll: true, onSuccess: () => muatKategori() });
}

// ---------- TAB KELOMPOK ----------
type KelRow = { id: number; nama_kelompok: string | null; id_sekolah: number | null; sekolah?: { nama_sekolah: string | null } | null };
const gSearch = ref('');
const gList = ref<Paginate<KelRow>>({ data: [], current_page: 1, last_page: 1, total: 0 });
const gPage = ref(1);
const gLoading = ref(false);
let gTimer: ReturnType<typeof setTimeout> | undefined;
watch(gSearch, () => { clearTimeout(gTimer); gTimer = setTimeout(() => { gPage.value = 1; muatKelompok(); }, 400); });
async function muatKelompok() {
    gLoading.value = true;
    try {
        const r = await fetch(`/kelompok-kategori/data?search=${encodeURIComponent(gSearch.value)}&page=${gPage.value}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        gList.value = await r.json();
    } catch { /* abaikan */ } finally { gLoading.value = false; }
}
const showKelForm = ref(false);
const kelEdit = ref<KelRow | null>(null);
const kelForm = reactive({ id_sekolah: '' as string | number, nama_kelompok: '' });
function bukaTambahKel() { kelEdit.value = null; kelForm.id_sekolah = ''; kelForm.nama_kelompok = ''; showKelForm.value = true; }
function bukaEditKel(r: KelRow) { kelEdit.value = r; kelForm.nama_kelompok = r.nama_kelompok ?? ''; showKelForm.value = true; }
function simpanKel() {
    const payload = {
        nama_kelompok: kelForm.nama_kelompok,
        ...(props.is_super_admin && kelForm.id_sekolah !== '' ? { id_sekolah: Number(kelForm.id_sekolah) } : {}),
    };
    if (kelEdit.value) router.put(`/kelompok-kategori/${kelEdit.value.id}`, payload, { preserveScroll: true, onSuccess: () => { showKelForm.value = false; muatKelompok(); } });
    else router.post('/kelompok-kategori', payload, { preserveScroll: true, onSuccess: () => { showKelForm.value = false; muatKelompok(); } });
}
async function hapusKel(id: number) {
    if (!(await konfirmasi('Hapus kelompok ini?'))) return;
    router.delete(`/kelompok-kategori/${id}`, { preserveScroll: true, onSuccess: () => muatKelompok() });
}

onMounted(() => { muatProduk(); muatKategori(); muatKelompok(); });

const bisaKelola = computed(() => props.can_manage !== false);

// ---------- Riwayat / kartu stok ----------
type KartuRow = { tanggal: string | null; keterangan: string; masuk: number; keluar: number; harga: number; sisa: number };
const showRiwayat = ref(false);
const riwayatLoading = ref(false);
const riwayatBarang = ref<{ nama: string | null; stok: number; satuan: string | null } | null>(null);
const riwayatKartu = ref<KartuRow[]>([]);
const riwayatMasuk = ref(0);
const riwayatKeluar = ref(0);

async function bukaRiwayat(r: BarangRow) {
    showRiwayat.value = true;
    riwayatLoading.value = true;
    riwayatKartu.value = [];
    try {
        const res = await fetch(`/produk/${r.id_barang}/riwayat`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const j = await res.json();
        riwayatBarang.value = j.barang;
        riwayatKartu.value = j.kartu;
        riwayatMasuk.value = j.total_masuk;
        riwayatKeluar.value = j.total_keluar;
    } catch { /* abaikan */ } finally { riwayatLoading.value = false; }
}

const formatTgl = (s: string | null) => {
    if (!s) return '-';
    const d = new Date(s.replace(' ', 'T'));
    return isNaN(d.getTime()) ? s : d.toLocaleString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const formatRp = (n: number) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');

const formatBarcode = (code?: string | null): string => {
    if (!code) return '-';
    const clean = String(code).replace(/\s+/g, '');
    if (/^\d{13}$/.test(clean)) {
        return `${clean.slice(0, 1)} ${clean.slice(1, 7)} ${clean.slice(7)}`;
    }
    return String(code);
};

function generateEan13() {
    // Prefix 899 (Indonesia) + 3988 + 5 digit nomor acak
    const base = '8993988' + Math.floor(10000 + Math.random() * 90000).toString();
    let sum = 0;
    for (let i = 0; i < 12; i++) {
        const digit = parseInt(base[i], 10);
        sum += (i % 2 === 0) ? digit : (digit * 3);
    }
    const check = (10 - (sum % 10)) % 10;
    f.barcode = base + check;
}

function salinBarcode() {
    const teks = detailRow.value?.barcode ?? '';
    if (!teks) return;
    try {
        window.navigator.clipboard?.writeText(teks);
    } catch {
        /* abaikan */
    }
}
</script>

<template>
    <Head title="Produk" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-emerald-800">Data Produk</h1>
            <p class="mt-0.5 text-sm text-neutral-500">
                Kelola produk, kategori & kelompok kategori
                <span v-if="sekolah?.nama_sekolah" class="font-medium text-neutral-700"> — {{ sekolah.nama_sekolah }}</span>
            </p>
        </div>

        <div class="grid w-full grid-cols-3 gap-1 rounded-lg bg-neutral-100 p-1 sm:w-fit">
            <button type="button" class="flex min-h-11 flex-col items-center justify-center gap-1 rounded-md px-1 py-2 text-center text-xs font-medium transition sm:flex-row sm:gap-1.5 sm:px-3 sm:py-1.5 sm:text-sm" :class="tab === 'produk' ? 'bg-emerald-600 text-white shadow-sm' : 'text-neutral-500 hover:text-emerald-700'" @click="tab = 'produk'">
                <ShoppingBag class="h-4 w-4 shrink-0" /> <span class="leading-tight">Daftar Produk</span>
            </button>
            <button type="button" class="flex min-h-11 flex-col items-center justify-center gap-1 rounded-md px-1 py-2 text-center text-xs font-medium transition sm:flex-row sm:gap-1.5 sm:px-3 sm:py-1.5 sm:text-sm" :class="tab === 'kategori' ? 'bg-emerald-600 text-white shadow-sm' : 'text-neutral-500 hover:text-emerald-700'" @click="tab = 'kategori'">
                <FolderTree class="h-4 w-4 shrink-0" /> <span class="leading-tight">Kategori</span>
            </button>
            <button type="button" class="flex min-h-11 flex-col items-center justify-center gap-1 rounded-md px-1 py-2 text-center text-xs font-medium transition sm:flex-row sm:gap-1.5 sm:px-3 sm:py-1.5 sm:text-sm" :class="tab === 'kelompok' ? 'bg-emerald-600 text-white shadow-sm' : 'text-neutral-500 hover:text-emerald-700'" @click="tab = 'kelompok'">
                <Layers class="h-4 w-4 shrink-0" /> <span class="leading-tight">Kelompok</span>
            </button>
        </div>

        <div v-if="successMsg" class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">{{ successMsg }}</div>
        <div v-if="formErrors.produk || formErrors.kategori || formErrors.kelompok" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            {{ formErrors.produk ?? formErrors.kategori ?? formErrors.kelompok }}
        </div>

        <!-- TAB PRODUK -->
        <div v-if="tab === 'produk'" class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-1 flex-col gap-2 sm:flex-row">
                    <div class="relative w-full sm:max-w-xs">
                        <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-emerald-600" />
                        <Input v-model="pSearch" placeholder="Cari produk..." class="pl-9" />
                    </div>
                    <select v-model="pKategori" class="h-9 w-full rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20 sm:w-auto" @change="pPage = 1; muatProduk()">
                        <option value="">Semua Kategori</option>
                        <option v-for="k in kategori_list" :key="k.id_kategori" :value="k.id_kategori">{{ k.nama }}</option>
                    </select>
                    <select v-model="pStatus" class="h-9 w-full rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20 sm:w-auto" @change="pPage = 1; muatProduk()">
                        <option value="semua">Semua Status</option>
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <Button v-if="bisaKelola" type="button" variant="outline" class="h-11 w-full border-emerald-200 text-emerald-700 hover:bg-emerald-50 sm:w-auto" @click="bukaScanProduk(true)">
                        <ScanBarcode class="h-4 w-4" /> Scan
                    </Button>
                    <Button v-if="bisaKelola" type="button" class="h-11 w-full bg-emerald-600 hover:bg-emerald-700 lg:w-auto" @click="bukaTambah">+ Tambah Produk</Button>
                </div>
            </div>

            <div v-if="pLoading" class="space-y-2 py-4">
                <Skeleton v-for="i in 3" :key="i" class="h-12 w-full" />
            </div>
            <div v-else-if="pList.data.length === 0" class="mt-3 rounded-lg bg-neutral-50 px-4 py-8 text-center text-sm text-neutral-400">Belum ada produk.</div>
            <div v-else class="mt-3">
            <!-- Kartu mobile -->
            <div class="space-y-2 sm:hidden">
                <div v-for="(r, i) in pList.data" :key="r.id_barang" class="rounded-lg border border-neutral-100 px-3 py-2.5 text-sm">
                    <div class="flex items-center gap-3">
                        <img v-if="r.foto" :src="fotoUrl(r.foto) ?? undefined" :alt="r.nama ?? ''" class="h-11 w-11 shrink-0 rounded-lg border border-neutral-200 object-cover" loading="lazy" />
                        <span v-else class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-neutral-100 text-neutral-300"><ShoppingBag class="h-5 w-5" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-neutral-900">{{ r.nama }}</p>
                            <p class="mt-0.5 text-xs text-neutral-400">{{ r.kategori?.nama ?? '-' }} · Stok <span class="font-semibold" :class="r.stok <= 0 ? 'text-red-600' : r.stok <= 5 ? 'text-amber-600' : 'text-neutral-700'">{{ r.stok }}</span></p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="font-bold whitespace-nowrap text-neutral-900">{{ formatRp(r.harga_jual) }}</p>
                            <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-semibold" :class="r.is_active ? 'bg-green-100 text-green-700' : 'bg-neutral-100 text-neutral-500'">{{ r.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                        </div>
                    </div>
                    <div class="mt-2 flex gap-1 overflow-x-auto border-t border-neutral-50 pt-2">
                        <button type="button" class="flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-xs font-medium text-neutral-700 hover:bg-neutral-100" @click="bukaDetail(r)">Detail</button>
                        <button type="button" class="flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-xs font-medium text-emerald-700 hover:bg-emerald-50" @click="bukaRiwayat(r)">Riwayat</button>
                        <template v-if="bisaKelola">
                            <button type="button" class="flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-xs font-medium text-neutral-700 hover:bg-neutral-100" @click="bukaEdit(r)">Edit</button>
                            <button type="button" class="flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-xs font-medium text-amber-600 hover:bg-amber-50" @click="toggleProduk(r.id_barang)">{{ r.is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                            <button type="button" class="flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-xs font-medium text-red-600 hover:bg-red-50" @click="hapusProduk(r.id_barang)">Hapus</button>
                        </template>
                    </div>
                </div>
            </div>
            <div class="hidden overflow-x-auto sm:block">
                <table class="w-full min-w-200 text-left text-sm">
                    <thead>
                        <tr class="border-b border-neutral-100 text-xs text-neutral-400">
                            <th class="py-2 pr-2 font-medium">No</th>
                            <th class="py-2 pr-2 font-medium">Foto</th>
                            <th class="py-2 pr-2 font-medium">Barcode</th>
                            <th class="py-2 pr-2 font-medium">Nama Produk</th>
                            <th class="py-2 pr-2 font-medium">Kategori</th>
                            <th class="py-2 pr-2 text-right font-medium">Harga Jual</th>
                            <th class="py-2 pr-2 text-center font-medium">Stok</th>
                            <th class="py-2 pr-2 text-center font-medium">Status</th>
                            <th class="py-2 text-center font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, i) in pList.data" :key="r.id_barang" class="border-b border-neutral-50 last:border-0 hover:bg-neutral-50">
                            <td class="py-2 pr-2 text-neutral-500">{{ (pList.current_page - 1) * 10 + i + 1 }}</td>
                            <td class="py-2 pr-2">
                                <img v-if="r.foto" :src="fotoUrl(r.foto) ?? undefined" :alt="r.nama ?? ''" class="h-10 w-10 rounded-lg border border-neutral-200 object-cover" loading="lazy" />
                                <span v-else class="flex h-10 w-10 items-center justify-center rounded-lg bg-neutral-100 text-neutral-300"><ShoppingBag class="h-5 w-5" /></span>
                            </td>
                            <td class="py-2 pr-2 whitespace-nowrap">
                                <span v-if="r.barcode" class="font-mono text-xs font-semibold text-neutral-800 bg-neutral-100 px-2 py-0.5 rounded border border-neutral-200">
                                    {{ formatBarcode(r.barcode) }}
                                </span>
                                <span v-else class="text-neutral-400">-</span>
                            </td>
                            <td class="py-2 pr-2 font-medium text-neutral-900">{{ r.nama }}</td>
                            <td class="py-2 pr-2 text-neutral-600">{{ r.kategori?.nama ?? '-' }}</td>
                            <td class="py-2 pr-2 text-right font-semibold whitespace-nowrap">{{ formatRp(r.harga_jual) }}</td>
                            <td class="py-2 pr-2 text-center font-semibold" :class="r.stok <= 0 ? 'text-red-600' : r.stok <= 5 ? 'text-amber-600' : 'text-neutral-700'">{{ r.stok }}</td>
                            <td class="py-2 pr-2 text-center">
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="r.is_active ? 'bg-green-100 text-green-700' : 'bg-neutral-100 text-neutral-500'">
                                    {{ r.is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="py-2 text-center whitespace-nowrap">
                                <button type="button" class="rounded-md px-2 py-1 text-xs font-medium text-neutral-700 hover:bg-neutral-100" @click="bukaDetail(r)">Detail</button>
                                <button type="button" class="rounded-md px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-50" @click="bukaRiwayat(r)">Riwayat</button>
                                <template v-if="bisaKelola">
                                    <button type="button" class="rounded-md px-2 py-1 text-xs font-medium text-neutral-700 hover:bg-neutral-100" @click="bukaEdit(r)">Edit</button>
                                    <button type="button" class="rounded-md px-2 py-1 text-xs font-medium text-amber-600 hover:bg-amber-50" @click="toggleProduk(r.id_barang)">{{ r.is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                    <button type="button" class="rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50" @click="hapusProduk(r.id_barang)">Hapus</button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <div class="mt-3 flex items-center justify-between text-sm text-neutral-500">
                    <span>Total {{ pList.total }} produk</span>
                    <div class="flex items-center gap-2">
                        <button type="button" class="flex h-10 min-w-10 items-center justify-center rounded-md border border-neutral-200 px-3 disabled:opacity-40" :disabled="pPage <= 1" @click="pPage--; muatProduk()">‹</button>
                        <span>{{ pPage }} / {{ pList.last_page }}</span>
                        <button type="button" class="flex h-10 min-w-10 items-center justify-center rounded-md border border-neutral-200 px-3 disabled:opacity-40" :disabled="pPage >= pList.last_page" @click="pPage++; muatProduk()">›</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB KATEGORI -->
        <div v-else-if="tab === 'kategori'" class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="relative w-full sm:max-w-xs">
                    <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-emerald-600" />
                    <Input v-model="kSearch" placeholder="Cari kategori..." class="pl-9" />
                </div>
                <Button v-if="bisaKelola" type="button" class="h-11 w-full bg-emerald-600 hover:bg-emerald-700 sm:w-auto" @click="bukaTambahKat">+ Tambah Kategori</Button>
            </div>
            <div v-if="kLoading" class="space-y-2 py-4">
                <Skeleton v-for="i in 3" :key="i" class="h-10 w-full" />
            </div>
            <div v-else-if="kList.data.length === 0" class="mt-3 rounded-lg bg-neutral-50 px-4 py-8 text-center text-sm text-neutral-400">Belum ada kategori.</div>
            <div v-else class="mt-3">
            <div class="space-y-2 sm:hidden">
                <div v-for="(r, i) in kList.data" :key="r.id_kategori" class="flex items-center gap-2 rounded-lg border border-neutral-100 px-3 py-2.5 text-sm">
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-neutral-900">{{ r.nama }}</p>
                        <p class="mt-0.5 truncate text-xs text-neutral-400">{{ r.kelompok?.nama_kelompok ?? '-' }}</p>
                    </div>
                    <template v-if="bisaKelola">
                        <button type="button" class="flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-xs font-medium text-neutral-700 hover:bg-neutral-100" @click="bukaEditKat(r)">Edit</button>
                        <button type="button" class="flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-xs font-medium text-red-600 hover:bg-red-50" @click="hapusKat(r.id_kategori)">Hapus</button>
                    </template>
                </div>
            </div>
            <div class="hidden overflow-x-auto sm:block">
                <table class="w-full min-w-140 text-left text-sm">
                    <thead>
                        <tr class="border-b border-neutral-100 text-xs text-neutral-400">
                            <th class="py-2 pr-2 font-medium">No</th>
                            <th class="py-2 pr-2 font-medium">Nama Kategori</th>
                            <th class="py-2 pr-2 font-medium">Kelompok Kategori</th>
                            <th class="py-2 text-center font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, i) in kList.data" :key="r.id_kategori" class="border-b border-neutral-50 last:border-0 hover:bg-neutral-50">
                            <td class="py-2 pr-2 text-neutral-500">{{ (kList.current_page - 1) * 10 + i + 1 }}</td>
                            <td class="py-2 pr-2 font-medium text-neutral-900">{{ r.nama }}</td>
                            <td class="py-2 pr-2 text-neutral-600">{{ r.kelompok?.nama_kelompok ?? '-' }}</td>
                            <td class="py-2 text-center whitespace-nowrap">
                                <template v-if="bisaKelola">
                                    <button type="button" class="rounded-md px-2 py-1 text-xs font-medium text-neutral-700 hover:bg-neutral-100" @click="bukaEditKat(r)">Edit</button>
                                    <button type="button" class="rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50" @click="hapusKat(r.id_kategori)">Hapus</button>
                                </template>
                                <span v-else class="text-xs text-neutral-300">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <div class="mt-3 flex items-center justify-between text-sm text-neutral-500">
                    <span>Total {{ kList.total }} kategori</span>
                    <div class="flex items-center gap-2">
                        <button type="button" class="flex h-10 min-w-10 items-center justify-center rounded-md border border-neutral-200 px-3 disabled:opacity-40" :disabled="kPage <= 1" @click="kPage--; muatKategori()">‹</button>
                        <span>{{ kPage }} / {{ kList.last_page }}</span>
                        <button type="button" class="flex h-10 min-w-10 items-center justify-center rounded-md border border-neutral-200 px-3 disabled:opacity-40" :disabled="kPage >= kList.last_page" @click="kPage++; muatKategori()">›</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB KELOMPOK -->
        <div v-else class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="relative w-full sm:max-w-xs">
                    <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-emerald-600" />
                    <Input v-model="gSearch" placeholder="Cari kelompok..." class="pl-9" />
                </div>
                <Button v-if="bisaKelola" type="button" class="h-11 w-full bg-emerald-600 hover:bg-emerald-700 sm:w-auto" @click="bukaTambahKel">+ Tambah Kelompok</Button>
            </div>
            <div v-if="gLoading" class="space-y-2 py-4">
                <Skeleton v-for="i in 3" :key="i" class="h-10 w-full" />
            </div>
            <div v-else-if="gList.data.length === 0" class="mt-3 rounded-lg bg-neutral-50 px-4 py-8 text-center text-sm text-neutral-400">Belum ada kelompok.</div>
            <div v-else class="mt-3">
            <div class="space-y-2 sm:hidden">
                <div v-for="(r, i) in gList.data" :key="r.id" class="flex items-center gap-2 rounded-lg border border-neutral-100 px-3 py-2.5 text-sm">
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-neutral-900">{{ r.nama_kelompok }}</p>
                        <p class="mt-0.5 truncate text-xs text-neutral-400">{{ r.sekolah?.nama_sekolah ?? '-' }}</p>
                    </div>
                    <template v-if="bisaKelola">
                        <button type="button" class="flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-xs font-medium text-neutral-700 hover:bg-neutral-100" @click="bukaEditKel(r)">Edit</button>
                        <button type="button" class="flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-xs font-medium text-red-600 hover:bg-red-50" @click="hapusKel(r.id)">Hapus</button>
                    </template>
                </div>
            </div>
            <div class="hidden overflow-x-auto sm:block">
                <table class="w-full min-w-140 text-left text-sm">
                    <thead>
                        <tr class="border-b border-neutral-100 text-xs text-neutral-400">
                            <th class="py-2 pr-2 font-medium">No</th>
                            <th class="py-2 pr-2 font-medium">Nama Kelompok</th>
                            <th class="py-2 pr-2 font-medium">Sekolah</th>
                            <th class="py-2 text-center font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, i) in gList.data" :key="r.id" class="border-b border-neutral-50 last:border-0 hover:bg-neutral-50">
                            <td class="py-2 pr-2 text-neutral-500">{{ (gList.current_page - 1) * 10 + i + 1 }}</td>
                            <td class="py-2 pr-2 font-medium text-neutral-900">{{ r.nama_kelompok }}</td>
                            <td class="py-2 pr-2 text-neutral-600">{{ r.sekolah?.nama_sekolah ?? '-' }}</td>
                            <td class="py-2 text-center whitespace-nowrap">
                                <template v-if="bisaKelola">
                                    <button type="button" class="rounded-md px-2 py-1 text-xs font-medium text-neutral-700 hover:bg-neutral-100" @click="bukaEditKel(r)">Edit</button>
                                    <button type="button" class="rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50" @click="hapusKel(r.id)">Hapus</button>
                                </template>
                                <span v-else class="text-xs text-neutral-300">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <div class="mt-3 flex items-center justify-between text-sm text-neutral-500">
                    <span>Total {{ gList.total }} kelompok</span>
                    <div class="flex items-center gap-2">
                        <button type="button" class="flex h-10 min-w-10 items-center justify-center rounded-md border border-neutral-200 px-3 disabled:opacity-40" :disabled="gPage <= 1" @click="gPage--; muatKelompok()">‹</button>
                        <span>{{ gPage }} / {{ gList.last_page }}</span>
                        <button type="button" class="flex h-10 min-w-10 items-center justify-center rounded-md border border-neutral-200 px-3 disabled:opacity-40" :disabled="gPage >= gList.last_page" @click="gPage++; muatKelompok()">›</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal form produk -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 p-4" @click.self="showForm = false">
        <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 shadow-xl">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-emerald-800">{{ editRow ? 'Edit Produk' : 'Tambah Produk' }}</h3>
                <button type="button" class="text-neutral-400 hover:text-emerald-700" @click="showForm = false"><X class="h-5 w-5" /></button>
            </div>
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div v-if="is_super_admin" class="sm:col-span-2">
                    <Label>Sekolah (Tenant)</Label>
                    <select v-model="f.id_sekolah" class="mt-1.5 w-full h-9 rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20">
                        <option value="">-- Ikut sekolah saya --</option>
                        <option v-for="s in sekolah_list" :key="s.id_sekolah" :value="s.id_sekolah">{{ s.nama_sekolah }}</option>
                    </select>
                </div>
                <div>
                    <div class="flex items-center justify-between gap-2">
                        <Label>Barcode (EAN-13)</Label>
                        <div class="flex items-center gap-2">
                            <button v-if="!editRow" type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 hover:text-emerald-700 hover:underline" @click="bukaScanProduk(false)">
                                <ScanBarcode class="h-3.5 w-3.5" /> Scan
                            </button>
                            <button type="button" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 hover:underline" @click="generateEan13">
                                + Buat EAN-13
                            </button>
                        </div>
                    </div>
                    <Input v-model="f.barcode" class="mt-1.5 font-mono" placeholder="Contoh: 8993988283294" />
                    <p v-if="infoScan" class="mt-1 text-xs font-medium text-emerald-700">{{ infoScan }}</p>
                    <InputError :message="formErrors.barcode" />
                </div>
                <div><Label>Satuan</Label><Input v-model="f.satuan" class="mt-1.5" placeholder="pcs" /></div>
                <div class="sm:col-span-2">
                    <Label>Foto Produk (jpg/png/webp, maks 2MB)</Label>
                    <div class="mt-1.5 flex items-center gap-3">
                        <img v-if="fotoPreview" :src="fotoPreview" alt="Preview" class="h-16 w-16 rounded-lg border border-neutral-200 object-cover" />
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="text-sm text-neutral-500 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-600 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white hover:file:bg-emerald-700" @change="pilihFoto" />
                    </div>
                    <InputError :message="formErrors.foto" />
                </div>
                <div class="sm:col-span-2"><Label>Nama Produk</Label><Input v-model="f.nama" class="mt-1.5" placeholder="Nama produk" /><InputError :message="formErrors.nama" /></div>
                <div>
                    <Label>Kategori</Label>
                    <select v-model="f.id_kategori" class="mt-1.5 w-full h-9 rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20">
                        <option value="">-- Pilih --</option>
                        <option v-for="k in kategori_list" :key="k.id_kategori" :value="k.id_kategori">{{ k.nama }}</option>
                    </select>
                </div>
                <div>
                    <Label>Kelompok</Label>
                    <select v-model="f.id_kelompok_kategori" class="mt-1.5 w-full h-9 rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20">
                        <option value="">-- Pilih --</option>
                        <option v-for="k in kelompok_list" :key="k.id" :value="k.id">{{ k.nama_kelompok }}</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <Label>Supplier</Label>
                    <select v-model="f.id_supplier" class="mt-1.5 w-full h-9 rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20">
                        <option value="">-- Pilih --</option>
                        <option v-for="s in supplier_list" :key="s.id_supplier" :value="s.id_supplier">{{ s.nama }}</option>
                    </select>
                </div>
                <div><Label>Harga Beli</Label><Input v-model.number="f.harga_beli" type="number" min="0" class="mt-1.5" /></div>
                <div><Label>Harga Jual</Label><Input v-model.number="f.harga_jual" type="number" min="0" class="mt-1.5" /><InputError :message="formErrors.harga_jual" /></div>
                <div><Label>Stok</Label><Input v-model.number="f.stok" type="number" min="0" class="mt-1.5" /><InputError :message="formErrors.stok" /></div>
                <div class="flex items-end pb-1">
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-neutral-700">
                        <input v-model="f.is_active" type="checkbox" class="h-4 w-4 rounded" /> Aktif
                    </label>
                </div>
            </div>
            <Button type="button" class="mt-4 w-full bg-emerald-600 hover:bg-emerald-700" @click="simpanProduk">Simpan</Button>
        </div>
    </div>

    <!-- Modal detail -->
    <div v-if="showDetail && detailRow" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 p-4" @click.self="showDetail = false">
        <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-emerald-800">Detail Produk</h3>
                <button type="button" class="text-neutral-400 hover:text-emerald-700" @click="showDetail = false"><X class="h-5 w-5" /></button>
            </div>
            <div v-if="detailRow.foto" class="mt-4 flex justify-center">
                <img :src="fotoUrl(detailRow.foto) ?? undefined" :alt="detailRow.nama ?? ''" class="h-32 w-32 rounded-xl border border-neutral-200 object-cover" />
            </div>
            <div v-if="detailRow.barcode" class="mt-3 flex flex-col items-center">
                <BarcodeView :value="detailRow.barcode" :height="40" />
                <button type="button" class="mt-1 inline-flex items-center gap-1 text-xs text-teal-600 hover:underline" @click="salinBarcode">
                    <Barcode class="h-3.5 w-3.5" /> Salin barcode
                </button>
            </div>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-neutral-400">Barcode</dt><dd class="font-medium font-mono">{{ formatBarcode(detailRow.barcode) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-neutral-400">Nama</dt><dd class="text-right font-medium">{{ detailRow.nama }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-neutral-400">Kategori</dt><dd>{{ detailRow.kategori?.nama ?? '-' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-neutral-400">Kelompok</dt><dd>{{ detailRow.kelompok_kategori?.nama_kelompok ?? '-' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-neutral-400">Supplier</dt><dd>{{ detailRow.supplier?.nama ?? '-' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-neutral-400">Satuan</dt><dd>{{ detailRow.satuan ?? '-' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-neutral-400">Harga Beli</dt><dd>{{ formatRp(detailRow.harga_beli) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-neutral-400">Harga Jual</dt><dd class="font-bold">{{ formatRp(detailRow.harga_jual) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-neutral-400">Stok</dt><dd class="font-bold">{{ detailRow.stok }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-neutral-400">Status</dt><dd>{{ detailRow.is_active ? 'Aktif' : 'Nonaktif' }}</dd></div>
            </dl>
        </div>
    </div>

    <!-- Modal form kategori -->
    <div v-if="showKatForm" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 p-4" @click.self="showKatForm = false">
        <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-emerald-800">{{ katEdit ? 'Edit Kategori' : 'Tambah Kategori' }}</h3>
                <button type="button" class="text-neutral-400 hover:text-emerald-700" @click="showKatForm = false"><X class="h-5 w-5" /></button>
            </div>
            <div class="mt-4 space-y-3">
                <div>
                    <Label>Kelompok Kategori</Label>
                    <select v-model="katForm.id_kelompok" class="mt-1.5 w-full h-9 rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20">
                        <option value="">-- Tanpa kelompok --</option>
                        <option v-for="k in kelompok_list" :key="k.id" :value="k.id">{{ k.nama_kelompok }}</option>
                    </select>
                    <InputError :message="formErrors.id_kelompok" />
                </div>
                <div><Label>Nama Kategori</Label><Input v-model="katForm.nama" class="mt-1.5" /><InputError :message="formErrors.nama" /></div>
            </div>
            <Button type="button" class="mt-4 w-full bg-emerald-600 hover:bg-emerald-700" @click="simpanKat">Simpan</Button>
        </div>
    </div>

    <!-- Modal form kelompok -->
    <div v-if="showKelForm" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 p-4" @click.self="showKelForm = false">
        <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-emerald-800">{{ kelEdit ? 'Edit Kelompok' : 'Tambah Kelompok' }}</h3>
                <button type="button" class="text-neutral-400 hover:text-emerald-700" @click="showKelForm = false"><X class="h-5 w-5" /></button>
            </div>
            <div class="mt-4 space-y-3">
                <div v-if="is_super_admin && !kelEdit">
                    <Label>Sekolah (Tenant)</Label>
                    <select v-model="kelForm.id_sekolah" class="mt-1.5 w-full h-9 rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20">
                        <option value="">-- Sekolah saya --</option>
                        <option v-for="s in sekolah_list" :key="s.id_sekolah" :value="s.id_sekolah">{{ s.nama_sekolah }}</option>
                    </select>
                </div>
                <div><Label>Nama Kelompok</Label><Input v-model="kelForm.nama_kelompok" class="mt-1.5" /><InputError :message="formErrors.nama_kelompok" /></div>
            </div>
            <Button type="button" class="mt-4 w-full bg-emerald-600 hover:bg-emerald-700" @click="simpanKel">Simpan</Button>
        </div>
    </div>

    <!-- Modal riwayat stok -->
    <div v-if="showRiwayat" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 p-4" @click.self="showRiwayat = false">
        <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 shadow-xl">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-emerald-800">Riwayat Stok</h3>
                <button type="button" class="text-neutral-400 hover:text-emerald-700" @click="showRiwayat = false"><X class="h-5 w-5" /></button>
            </div>
            <div v-if="riwayatLoading" class="py-8 text-center text-sm text-neutral-400">Memuat...</div>
            <div v-else>
                <p class="mt-2 text-sm font-semibold text-neutral-800">{{ riwayatBarang?.nama }} <span class="font-normal text-neutral-400">(stok saat ini: {{ riwayatBarang?.stok }} {{ riwayatBarang?.satuan ?? '' }})</span></p>
                <div v-if="riwayatKartu.length === 0" class="mt-3 rounded-lg bg-neutral-50 px-4 py-6 text-center text-sm text-neutral-400">
                    Belum ada pergerakan stok (pembelian selesai / penjualan).
                </div>
                <div v-else class="mt-3 overflow-x-auto">
                    <table class="w-full min-w-120 text-left text-sm">
                        <thead>
                            <tr class="border-b border-neutral-100 text-xs text-neutral-400">
                                <th class="py-2 pr-2 font-medium">Tanggal</th>
                                <th class="py-2 pr-2 font-medium">Keterangan</th>
                                <th class="py-2 pr-2 text-right font-medium">Masuk</th>
                                <th class="py-2 pr-2 text-right font-medium">Keluar</th>
                                <th class="py-2 text-right font-medium">Sisa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(k, i) in riwayatKartu" :key="i" class="border-b border-neutral-50 last:border-0">
                                <td class="py-1.5 pr-2 whitespace-nowrap text-neutral-500">{{ formatTgl(k.tanggal) }}</td>
                                <td class="py-1.5 pr-2 text-neutral-700">{{ k.keterangan }}</td>
                                <td class="py-1.5 pr-2 text-right font-medium text-green-600">{{ k.masuk > 0 ? `+${k.masuk}` : '-' }}</td>
                                <td class="py-1.5 pr-2 text-right font-medium text-red-500">{{ k.keluar > 0 ? `-${k.keluar}` : '-' }}</td>
                                <td class="py-1.5 text-right font-bold text-neutral-900">{{ k.sisa }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-neutral-200 text-sm font-bold">
                                <td colspan="2" class="py-2 pr-2">Total</td>
                                <td class="py-2 pr-2 text-right text-green-600">+{{ riwayatMasuk }}</td>
                                <td class="py-2 pr-2 text-right text-red-500">-{{ riwayatKeluar }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Pemindai barcode untuk tambah produk -->
    <BarcodeScanner :open="showScan" judul="Pindai Barcode Produk" @detected="onScanProduk" @close="showScan = false" />
</template>
