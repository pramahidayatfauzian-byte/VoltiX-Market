<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    Camera,
    Check,
    Flashlight,
    FlashlightOff,
    Image as ImageIcon,
    Keyboard,
    LayoutGrid,
    List,
    Loader2,
    Minus,
    Package,
    Plus,
    Printer,
    ReceiptText,
    RefreshCw,
    ScanBarcode,
    Search,
    ShoppingBag,
    Sparkles,
    SwitchCamera,
    Trash2,
    Upload,
    User as UserIcon,
    X,
    ZoomIn,
    ZoomOut,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getKeranjangItems, setKeranjangQty } from '@/composables/useKeranjang';
import { konfirmasi } from '@/composables/useConfirm';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Kasir / Transaksi', href: '/kasir' }],
    },
});

type Produk = {
    id_barang: number;
    id_kategori?: number | null;
    barcode: string | null;
    foto?: string | null;
    foto_url?: string | null;
    nama: string | null;
    satuan: string | null;
    harga_beli: number;
    harga_jual: number;
    stok: number;
    baru?: boolean;
};

type KandidatCepat = {
    barcode: string;
    nama: string | null;
    kategori: string;
    satuan: string;
    harga: number | null;
    sumber: string | null;
    barcode_valid: boolean;
    produk_indonesia: boolean;
};

type CartItem = Produk & {
    qty: number;
    diskon_tipe: '' | 'persen' | 'nominal';
    diskon_nilai: number;
};

type PelangganOpt = {
    id_pelanggan: number;
    nama_pelanggan: string | null;
    telepon: string | null;
    alamat: string | null;
    kelompok?: { nama_kelompok: string | null } | null;
};

type Kelompok = { id: number; nama_kelompok: string | null };
type Kategori = { id_kategori: number; nama: string | null };

const props = defineProps<{
    sekolah?: { id_sekolah: number; nama_sekolah: string | null } | null;
    kelompok_pelanggan: Kelompok[];
    kategori_list?: Kategori[];
    produk_list?: Produk[];
}>();

const page = usePage();
const flash = computed(() => page.props.flash as Record<string, unknown>);
const formErrors = computed(
    () => (page.props.errors ?? {}) as Record<string, string>,
);

const showSuccessAnimation = ref(false);

function triggerConfetti() {
    const canvas = document.createElement('canvas');
    canvas.style.position = 'fixed';
    canvas.style.inset = '0';
    canvas.style.zIndex = '99999';
    canvas.style.pointerEvents = 'none';
    document.body.appendChild(canvas);
    const rawCtx = canvas.getContext('2d');
    if (!rawCtx) { canvas.remove(); return; }
    const ctx: CanvasRenderingContext2D = rawCtx;
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;

    const particles: Array<{x: number; y: number; vx: number; vy: number; size: number; color: string; rot: number; vrot: number}> = [];
    const colors = ['#10b981', '#34d399', '#059669', '#fbbf24', '#3b82f6', '#ec4899'];
    for (let i = 0; i < 90; i++) {
        particles.push({
            x: canvas.width / 2,
            y: canvas.height / 2,
            vx: (Math.random() - 0.5) * 18,
            vy: (Math.random() - 0.7) * 18 - 4,
            size: Math.random() * 8 + 4,
            color: colors[Math.floor(Math.random() * colors.length)],
            rot: Math.random() * 360,
            vrot: (Math.random() - 0.5) * 12
        });
    }

    let frame = 0;
    function animate() {
        frame++;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        particles.forEach(p => {
            p.x += p.vx;
            p.y += p.vy;
            p.vy += 0.5;
            p.rot += p.vrot;
            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate((p.rot * Math.PI) / 180);
            ctx.fillStyle = p.color;
            ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.6);
            ctx.restore();
        });
        if (frame < 100) {
            requestAnimationFrame(animate);
        } else {
            canvas.remove();
        }
    }
    animate();
}

// ---------- Produk catalog & search ----------
const produkKeyword = ref('');
const kategoriDipilih = ref<number | null>(null);
const produkHasil = ref<Produk[]>(props.produk_list ? [...props.produk_list] : []);
const produkLoading = ref(false);
const viewMode = ref<'grid' | 'list'>('grid');
let produkTimer: ReturnType<typeof setTimeout> | undefined;

watch([produkKeyword, kategoriDipilih], ([keyword, kat]) => {
    clearTimeout(produkTimer);
    produkTimer = setTimeout(() => cariProduk(keyword, kat), 250);
});

async function cariProduk(keyword: string, idKategori?: number | null) {
    produkLoading.value = true;
    try {
        const params = new URLSearchParams();
        if (keyword) params.append('search', keyword);
        if (idKategori) params.append('id_kategori', String(idKategori));
        const r = await fetch(
            `/kasir/produk?${params.toString()}`,
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } },
        );
        const j = await r.json();
        produkHasil.value = j.data ?? [];
    } catch {
        produkHasil.value = [];
    } finally {
        produkLoading.value = false;
    }
}

function resetFilterProduk() {
    produkKeyword.value = '';
    kategoriDipilih.value = null;
    if (props.produk_list && props.produk_list.length > 0) {
        produkHasil.value = [...props.produk_list];
    } else {
        cariProduk('', null);
    }
}

function qtyDiKeranjang(id_barang: number): number {
    const item = cart.value.find((c) => c.id_barang === id_barang);
    return item ? item.qty : 0;
}

// ---------- Multi-Keranjang (Parkir / Slot Antrean) ----------
type CartSlot = {
    id: number;
    nama: string;
    cart: CartItem[];
    pelanggan: PelangganOpt | null;
};

const activeSlotId = ref<number>(1);
const slots = ref<CartSlot[]>([
    { id: 1, nama: 'Antrean 1', cart: [], pelanggan: null },
    { id: 2, nama: 'Antrean 2', cart: [], pelanggan: null },
    { id: 3, nama: 'Antrean 3', cart: [], pelanggan: null },
]);

// ---------- Keranjang Aktif ----------
const cart = ref<CartItem[]>([]);
const cartError = ref('');

// Undo hapus item (5 detik)
type DeletedItemRecord = {
    item: CartItem;
    index: number;
    timer?: ReturnType<typeof setTimeout>;
    interval?: ReturnType<typeof setInterval>;
    countdown: number;
};
const itemDihapus = ref<DeletedItemRecord | null>(null);

function bersihkanTimerUndo() {
    if (itemDihapus.value) {
        if (itemDihapus.value.timer) clearTimeout(itemDihapus.value.timer);
        if (itemDihapus.value.interval) clearInterval(itemDihapus.value.interval);
        itemDihapus.value = null;
    }
}

function tambahProduk(p: Produk): boolean {
    cartError.value = '';
    const ada = cart.value.find((c) => c.id_barang === p.id_barang);
    if (ada) {
        if (ada.qty + 1 > p.stok) {
            cartError.value = `Stok ${p.nama} tidak cukup (sisa ${p.stok}).`;
            return false;
        }
        ada.qty += 1;
    } else {
        if (p.stok < 1) {
            cartError.value = `Stok ${p.nama} habis.`;
            return false;
        }
        cart.value.push({
            ...p,
            foto_url: p.foto_url ?? (p.foto ? `/storage/${p.foto}` : null),
            qty: 1,
            diskon_tipe: '',
            diskon_nilai: 0,
        });
    }
    return true;
}

function ubahQty(item: CartItem, delta: number) {
    const baru = item.qty + delta;
    if (baru < 1) return;
    if (baru > item.stok) {
        cartError.value = `Stok ${item.nama} tidak cukup (sisa ${item.stok}).`;
        return;
    }
    cartError.value = '';
    item.qty = baru;
}

function onKetikQty(item: CartItem) {
    if (item.qty > item.stok) {
        cartError.value = `Stok ${item.nama} tidak cukup (maksimal ${item.stok}).`;
        item.qty = item.stok;
    } else if (item.qty < 1 && item.qty !== null && item.qty !== undefined && !isNaN(item.qty)) {
        item.qty = 1;
        cartError.value = '';
    } else {
        cartError.value = '';
    }
}

function onBlurQty(item: CartItem) {
    if (!item.qty || isNaN(item.qty) || item.qty < 1) {
        item.qty = 1;
    }
}

function hapusItem(id: number) {
    const idx = cart.value.findIndex((c) => c.id_barang === id);
    if (idx === -1) return;
    const target = cart.value[idx];

    bersihkanTimerUndo();
    cart.value.splice(idx, 1);

    const record: DeletedItemRecord = {
        item: { ...target },
        index: idx,
        countdown: 5,
    };

    record.interval = setInterval(() => {
        if (itemDihapus.value) {
            itemDihapus.value.countdown -= 1;
            if (itemDihapus.value.countdown <= 0) {
                bersihkanTimerUndo();
            }
        }
    }, 1000);

    record.timer = setTimeout(() => {
        bersihkanTimerUndo();
    }, 5000);

    itemDihapus.value = record;
}

function urungkanHapus() {
    if (!itemDihapus.value) return;
    const { item, index } = itemDihapus.value;
    bersihkanTimerUndo();
    const pos = Math.min(index, cart.value.length);
    cart.value.splice(pos, 0, item);
    cartError.value = '';
}

async function konfirmasiBersihkan() {
    if (cart.value.length === 0) return;
    if (cart.value.length > 1 || cart.value.some((i) => i.qty > 1)) {
        if (!(await konfirmasi(`Kosongkan antrean saat ini (${cart.value.length} jenis item)?`))) {
            return;
        }
    }
    bersihkan();
}

function gantiSlot(newId: number) {
    if (newId === activeSlotId.value) return;
    bersihkanTimerUndo();

    // Simpan data slot saat ini
    const cur = slots.value.find((s) => s.id === activeSlotId.value);
    if (cur) {
        cur.cart = [...cart.value];
        cur.pelanggan = pelangganDipilih.value;
    }

    // Pindah ke slot baru
    activeSlotId.value = newId;
    const target = slots.value.find((s) => s.id === newId);
    if (target) {
        cart.value = [...target.cart];
        pelangganDipilih.value = target.pelanggan;
    } else {
        cart.value = [];
        pelangganDipilih.value = null;
    }
    cartError.value = '';
    simpanSlotsKeStorage();
}

function parkirCepat() {
    const slotKosong = slots.value.find((s) => s.id !== activeSlotId.value && s.cart.length === 0);
    if (slotKosong) {
        gantiSlot(slotKosong.id);
    } else {
        const nextId = (activeSlotId.value % slots.value.length) + 1;
        gantiSlot(nextId);
    }
}

function lineDiskon(item: CartItem): number {
    const bruto = Number(item.harga_jual) * item.qty;
    if (item.diskon_tipe === 'persen' && item.diskon_nilai > 0)
        return Math.min(bruto, (bruto * item.diskon_nilai) / 100);
    if (item.diskon_tipe === 'nominal' && item.diskon_nilai > 0)
        return Math.min(bruto, item.diskon_nilai);
    return 0;
}

const subtotal = computed(() =>
    cart.value.reduce((s, i) => s + Number(i.harga_jual) * i.qty, 0),
);
const totalDiskon = computed(() =>
    cart.value.reduce((s, i) => s + lineDiskon(i), 0),
);
const total = computed(() => subtotal.value - totalDiskon.value);

// ---------- Pelanggan (panel kanan) ----------
const pelangganKeyword = ref('');
const pelangganHasil = ref<PelangganOpt[]>([]);
const pelangganDipilih = ref<PelangganOpt | null>(null);
let pelangganTimer: ReturnType<typeof setTimeout> | undefined;

watch(pelangganKeyword, (v) => {
    clearTimeout(pelangganTimer);
    pelangganTimer = setTimeout(() => cariPelanggan(v), 300);
});

async function cariPelanggan(keyword: string) {
    try {
        const r = await fetch(
            `/kasir/pelanggan-cari?search=${encodeURIComponent(keyword)}`,
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } },
        );
        const j = await r.json();
        pelangganHasil.value = j.data ?? [];
    } catch {
        pelangganHasil.value = [];
    }
}

function pilihPelanggan(p: PelangganOpt) {
    pelangganDipilih.value = p;
    pelangganKeyword.value = '';
    pelangganHasil.value = [];
}

// ---------- Pembayaran ----------
const showBayar = ref(false);
const bayar = reactive({
    jenis_transaksi: 'tunai',
    cara_bayar: 'cash',
    total_bayar: 0,
    status_pembayaran: 'sudah bayar',
    note: '',
});
const processing = ref(false);

function bukaBayar() {
    if (cart.value.length === 0) {
        cartError.value = 'Keranjang masih kosong.';
        return;
    }
    cartError.value = '';
    bayar.total_bayar = total.value;
    showBayar.value = true;
}

function bersihkan() {
    cart.value = [];
    pelangganDipilih.value = null;
    cartError.value = '';
    showBayar.value = false;
    setKeranjangQty(0);
}

const kembalian = computed(() => bayar.total_bayar - total.value);
const bayarValid = computed(() => {
    if (cart.value.length === 0) return false;
    if (
        bayar.status_pembayaran === 'sudah bayar' &&
        bayar.jenis_transaksi === 'tunai' &&
        bayar.total_bayar < total.value
    )
        return false;
    return true;
});

watch(
    () => cart.value.reduce((s, c) => s + c.qty, 0),
    (n) => setKeranjangQty(n),
    { immediate: true },
);

function simpanSlotsKeStorage() {
    try {
        const cur = slots.value.find((s) => s.id === activeSlotId.value);
        if (cur) {
            cur.cart = [...cart.value];
            cur.pelanggan = pelangganDipilih.value;
        }

        const data = {
            activeSlotId: activeSlotId.value,
            slots: slots.value.map((s) => ({
                id: s.id,
                nama: s.nama,
                cart: s.cart.map((i) => ({
                    id_barang: i.id_barang,
                    barcode: i.barcode,
                    foto: i.foto ?? null,
                    foto_url: i.foto_url ?? null,
                    nama: i.nama,
                    satuan: i.satuan ?? null,
                    harga_beli: Number(i.harga_beli || 0),
                    harga_jual: Number(i.harga_jual),
                    stok: Number(i.stok),
                    qty: Number(i.qty),
                    diskon_tipe: i.diskon_tipe || '',
                    diskon_nilai: Number(i.diskon_nilai || 0),
                })),
                pelanggan: s.pelanggan,
            })),
        };
        localStorage.setItem('pos_keranjang_slots', JSON.stringify(data));

        // Kompatibilitas legacy untuk useKeranjang & badge navbar
        const toStore = cart.value.map((i) => ({
            id_barang: i.id_barang,
            barcode: i.barcode,
            nama: i.nama,
            harga_jual: Number(i.harga_jual),
            stok: Number(i.stok),
            qty: Number(i.qty),
        }));
        localStorage.setItem('pos_keranjang', JSON.stringify(toStore));
        window.dispatchEvent(new Event('pos-keranjang'));
    } catch { /* abaikan */ }
}

watch(
    [cart, pelangganDipilih],
    () => {
        simpanSlotsKeStorage();
    },
    { deep: true },
);

function prosesBayar() {
    processing.value = true;
    router.post(
        '/kasir/checkout',
        {
            id_pelanggan: pelangganDipilih.value?.id_pelanggan ?? null,
            items: cart.value.map((c) => ({
                id_barang: c.id_barang,
                qty: c.qty,
                diskon_tipe: c.diskon_tipe === '' ? null : c.diskon_tipe,
                diskon_nilai: c.diskon_nilai || 0,
            })),
            jenis_transaksi: bayar.jenis_transaksi,
            cara_bayar: bayar.cara_bayar,
            total_bayar: bayar.total_bayar,
            status_pembayaran: bayar.status_pembayaran,
            note: bayar.note || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                triggerConfetti();
                showSuccessAnimation.value = true;
                bersihkan();
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

function muatKeranjangDariStorage() {
    try {
        const rawSlots = localStorage.getItem('pos_keranjang_slots');
        if (rawSlots) {
            const parsed = JSON.parse(rawSlots);
            if (Array.isArray(parsed.slots) && parsed.slots.length > 0) {
                slots.value = parsed.slots;
                activeSlotId.value = Number(parsed.activeSlotId) || 1;
                const active = slots.value.find((s) => s.id === activeSlotId.value) || slots.value[0];
                cart.value = active.cart || [];
                pelangganDipilih.value = active.pelanggan || null;
                if (cart.value.length > 0) cartError.value = '';
                return;
            }
        }

        // fallback ke legacy pos_keranjang
        const saved = getKeranjangItems();
        if (saved.length > 0 && cart.value.length === 0) {
            for (const s of saved) {
                cart.value.push({
                    id_barang: s.id_barang,
                    barcode: s.barcode,
                    foto: null,
                    foto_url: null,
                    nama: s.nama,
                    satuan: null,
                    harga_beli: 0,
                    harga_jual: s.harga_jual,
                    stok: s.stok,
                    qty: s.qty,
                    diskon_tipe: '',
                    diskon_nilai: 0,
                });
            }
            slots.value[0].cart = [...cart.value];
            if (cart.value.length > 0) cartError.value = '';
        }
    } catch { /* abaikan */ }
}

function onStorageEvent(e: StorageEvent) {
    if (e.key === 'pos_keranjang_slots') {
        muatKeranjangDariStorage();
    }
}

onMounted(() => {
    muatKeranjangDariStorage();
    if (!produkHasil.value || produkHasil.value.length === 0) {
        cariProduk('', null);
    }
    window.addEventListener('keydown', tombolCepat);
    window.addEventListener('storage', onStorageEvent);
    window.addEventListener('paste', onPasteWindow);
});

onUnmounted(() => {
    window.removeEventListener('keydown', tombolCepat);
    window.removeEventListener('storage', onStorageEvent);
    window.removeEventListener('paste', onPasteWindow);
    bersihkanTimerUndo();
    hentikanScan();
});

// ---------- Scan barcode via kamera & alat scan fisik ----------
const showScan = ref(false);
const videoRef = ref<HTMLVideoElement | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
const scanError = ref('');
const scanAktif = ref(false);
const scanLoading = ref(false);
const daftarKamera = ref<MediaDeviceInfo[]>([]);
const kameraDipilih = ref<string>('');
const scanBeruntun = ref(false);
const canTorch = ref(false);
const torchOn = ref(false);
const pesanScanTerakhir = ref<{ sukses: boolean; teks: string } | null>(null);
const scanManualInput = ref('');
const scanSuccessPulse = ref(false);
let scanSuccessPulseTimer: ReturnType<typeof setTimeout> | undefined;

// Fitur Zoom Kamera Scanner (Hardware & Digital)
const zoomLevel = ref<number>(1);
const minZoom = ref<number>(1);
const maxZoom = ref<number>(3);
const hasHardwareZoom = ref<boolean>(false);

async function setZoom(level: number) {
    const clamped = Math.max(minZoom.value, Math.min(maxZoom.value, Math.round(level * 10) / 10));
    zoomLevel.value = clamped;

    if (mediaStream && hasHardwareZoom.value) {
        try {
            const track = mediaStream.getVideoTracks()[0];
            if (track) {
                await (track as any).applyConstraints({
                    advanced: [{ zoom: clamped }],
                });
            }
        } catch (err) {
            console.warn('Gagal set hardware zoom:', err);
        }
    }
}

let touchStartDistance = 0;
let initialZoomOnTouch = 1;

function onTouchStartVideo(e: TouchEvent) {
    if (e.touches.length === 2) {
        touchStartDistance = Math.hypot(
            e.touches[0].clientX - e.touches[1].clientX,
            e.touches[0].clientY - e.touches[1].clientY,
        );
        initialZoomOnTouch = zoomLevel.value;
    }
}

function onTouchMoveVideo(e: TouchEvent) {
    if (e.touches.length === 2 && touchStartDistance > 0) {
        e.preventDefault();
        const dist = Math.hypot(
            e.touches[0].clientX - e.touches[1].clientX,
            e.touches[0].clientY - e.touches[1].clientY,
        );
        const factor = dist / touchStartDistance;
        setZoom(initialZoomOnTouch * factor);
    }
}

function onTouchEndVideo() {
    touchStartDistance = 0;
}

// ZXing dimuat malas (dynamic import) agar bundle awal halaman Kasir tetap ringan di HP.
// Pustaka ±400KB ini hanya diunduh saat pengguna membuka pemindai barcode.
type ZxingReader = { decodeFromCanvas(canvas: HTMLCanvasElement): { getText(): string } | null };
let codeReader: ZxingReader | null = null;
let zxingLoad: Promise<ZxingReader> | null = null;
async function getCodeReader(): Promise<ZxingReader> {
    if (codeReader) return codeReader;
    if (!zxingLoad) {
        zxingLoad = (async () => {
            const [{ BrowserMultiFormatReader }, { BarcodeFormat, DecodeHintType }] = await Promise.all([
                import('@zxing/browser'),
                import('@zxing/library'),
            ]);
            const hints = new Map();
            hints.set(DecodeHintType.TRY_HARDER, true);
            hints.set(DecodeHintType.POSSIBLE_FORMATS, [
                BarcodeFormat.CODE_128,
                BarcodeFormat.CODE_39,
                BarcodeFormat.CODE_93,
                BarcodeFormat.EAN_13,
                BarcodeFormat.EAN_8,
                BarcodeFormat.UPC_A,
                BarcodeFormat.UPC_E,
                BarcodeFormat.ITF,
                BarcodeFormat.QR_CODE,
            ]);
            codeReader = new BrowserMultiFormatReader(hints) as unknown as ZxingReader;
            return codeReader;
        })();
    }
    return zxingLoad;
}
async function listVideoDevices(): Promise<MediaDeviceInfo[]> {
    const { BrowserCodeReader } = await import('@zxing/browser');
    return BrowserCodeReader.listVideoInputDevices();
}

let mediaStream: MediaStream | null = null;
let scanLoopTimer: ReturnType<typeof setInterval> | undefined;
let isHandlingResult = false;
let barcodeTerakhirDiScan = '';
let waktuScanTerakhir = 0;
let timerPesanScan: ReturnType<typeof setTimeout> | undefined;

// Buffer penangkap scanner barcode fisik (hardware barcode gun)
let barcodeScannerBuffer = '';
let barcodeScannerLastTime = 0;
let barcodeScannerTimer: ReturnType<typeof setTimeout> | undefined;

function bunyiBeep() {
    try {
        const AudioCtx = window.AudioContext || (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        if (ctx.state === 'suspended') {
            ctx.resume();
        }
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(1800, ctx.currentTime);
        gain.gain.setValueAtTime(0.25, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.12);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.12);
    } catch {
        // Abaikan jika audio context diblokir browser
    }
}

function tanganiErrorKamera(err: unknown) {
    console.error('Kamera error:', err);
    if (!err || typeof err !== 'object') {
        scanError.value = 'Gagal mengakses kamera.';
        return;
    }
    const errObj = err as { name?: string; message?: string };
    const nama = errObj.name || '';
    if (nama === 'NotAllowedError' || nama === 'PermissionDeniedError') {
        scanError.value = 'Izin kamera ditolak. Berikan izin akses kamera pada pengaturan browser.';
    } else if (nama === 'NotFoundError' || nama === 'DevicesNotFoundError') {
        scanError.value = 'Tidak ada perangkat kamera yang terdeteksi pada perangkat ini.';
    } else if (nama === 'NotReadableError' || nama === 'TrackStartError') {
        scanError.value = 'Kamera sedang digunakan oleh aplikasi lain atau tidak dapat diakses.';
    } else if (nama === 'OverconstrainedError') {
        kameraDipilih.value = '';
        mulaiKamera();
    } else {
        scanError.value = `Gagal mengaktifkan kamera: ${errObj.message || nama || 'Error tidak diketahui'}`;
    }
}

async function toggleTorch() {
    if (!mediaStream) return;
    const track = mediaStream.getVideoTracks()[0];
    if (!track) return;
    try {
        const next = !torchOn.value;
        await (track as any).applyConstraints({
            advanced: [{ torch: next }],
        });
        torchOn.value = next;
    } catch (e) {
        console.warn('Gagal toggle senter:', e);
    }
}

async function mulaiKamera() {
    hentikanScan();
    scanLoading.value = true;
    scanError.value = '';
    canTorch.value = false;
    torchOn.value = false;

    if (!videoRef.value) {
        scanLoading.value = false;
        return;
    }

    try {
        const constraints: MediaStreamConstraints = {
            video: {
                deviceId: kameraDipilih.value ? { exact: kameraDipilih.value } : undefined,
                facingMode: kameraDipilih.value ? undefined : { ideal: 'environment' },
                width: { ideal: 1280, min: 640 },
                height: { ideal: 720, min: 480 },
            },
            audio: false,
        };

        let stream: MediaStream;
        try {
            stream = await navigator.mediaDevices.getUserMedia(constraints);
        } catch (constraintErr) {
            console.warn('Fallback ke konstrain kamera standar:', constraintErr);
            stream = await navigator.mediaDevices.getUserMedia({
                video: kameraDipilih.value ? { deviceId: { exact: kameraDipilih.value } } : { facingMode: 'environment' },
                audio: false,
            });
        }

        mediaStream = stream;
        if (!videoRef.value) return;
        videoRef.value.srcObject = stream;
        await videoRef.value.play();

        // Periksa apakah kamera mendukung flash/senter & zoom hardware
        try {
            const track = stream.getVideoTracks()[0];
            const caps = (track as any)?.getCapabilities?.();
            if (caps && 'torch' in caps) {
                canTorch.value = true;
            }
            if (caps && 'zoom' in caps) {
                hasHardwareZoom.value = true;
                minZoom.value = Number(caps.zoom.min) || 1;
                maxZoom.value = Math.min(Number(caps.zoom.max) || 5, 5);
                zoomLevel.value = Math.max(minZoom.value, 1);
            } else {
                hasHardwareZoom.value = false;
                minZoom.value = 1;
                maxZoom.value = 3;
                zoomLevel.value = 1;
            }
        } catch {
            hasHardwareZoom.value = false;
            minZoom.value = 1;
            maxZoom.value = 3;
            zoomLevel.value = 1;
        }

        // Tunggu hingga frame video memiliki ukuran piksel nyata
        await new Promise<void>((resolve) => {
            if (videoRef.value && videoRef.value.videoWidth > 0) {
                return resolve();
            }
            const onLoaded = () => {
                videoRef.value?.removeEventListener('loadeddata', onLoaded);
                resolve();
            };
            videoRef.value?.addEventListener('loadeddata', onLoaded);
            setTimeout(resolve, 600);
        });

        scanAktif.value = true;
        scanLoading.value = false;

        // Inisialisasi Native BarcodeDetector (jika didukung browser secara aman)
        let nativeDetector: any = null;
        if (typeof window !== 'undefined' && 'BarcodeDetector' in window) {
            try {
                const supported: string[] = await (window as any).BarcodeDetector.getSupportedFormats();
                const wanted = ['code_128', 'code_39', 'code_93', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'itf', 'qr_code'];
                const valid = wanted.filter((f) => supported.includes(f));
                if (valid.length > 0) {
                    nativeDetector = new (window as any).BarcodeDetector({ formats: valid });
                }
            } catch (detectorErr) {
                console.warn('Native BarcodeDetector tidak didukung:', detectorErr);
                nativeDetector = null;
            }
        }

        // Siapkan kanvas pemrosesan frame (Center ROI & Full Frame)
        let roiCanvas: HTMLCanvasElement | null = null;
        let roiCtx: CanvasRenderingContext2D | null = null;
        let fullCanvas: HTMLCanvasElement | null = null;
        let fullCtx: CanvasRenderingContext2D | null = null;
        let rotCanvas: HTMLCanvasElement | null = null;
        let rotCtx: CanvasRenderingContext2D | null = null;
        let scanCycle = 0;
        let isDecoding = false;

        // Loop pemindaian frame (berjalan setiap 150ms)
        scanLoopTimer = setInterval(async () => {
            if (!videoRef.value || !scanAktif.value || isDecoding || isHandlingResult) return;
            const video = videoRef.value;
            if (video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA || video.videoWidth === 0) return;

            isDecoding = true;
            scanCycle++;
            try {
                // 1. Coba Native BarcodeDetector terlebih dahulu (akselerasi hardware OS)
                if (nativeDetector) {
                    try {
                        const hasil = await nativeDetector.detect(video);
                        if (hasil.length > 0 && hasil[0]?.rawValue) {
                            await onScanResult(hasil[0].rawValue);
                            isDecoding = false;
                            return;
                        }
                    } catch {
                        // abaikan kegagalan native, lanjut ke zxing
                    }
                }

                // 2. Pemindaian via ZXing: Prioritaskan Area Bidik Tengah (Center ROI)
                const vw = video.videoWidth;
                const vh = video.videoHeight;
                // Jika zoom aktif tapi kamera tidak mendukung hardware zoom, sesuaikan ROI digital
                const zoomFactor = hasHardwareZoom.value ? 1 : Math.max(1, zoomLevel.value);
                const cropW = Math.max(80, Math.round((vw * 0.7) / zoomFactor));
                const cropH = Math.max(60, Math.round((vh * 0.55) / zoomFactor));
                const cropX = Math.round((vw - cropW) / 2);
                const cropY = Math.round((vh - cropH) / 2);

                const targetRoiW = Math.min(cropW, 520);
                const targetRoiH = Math.round((targetRoiW / cropW) * cropH);

                if (!roiCanvas) roiCanvas = document.createElement('canvas');
                if (roiCanvas.width !== targetRoiW || roiCanvas.height !== targetRoiH) {
                    roiCanvas.width = targetRoiW;
                    roiCanvas.height = targetRoiH;
                    roiCtx = roiCanvas.getContext('2d', { willReadFrequently: true });
                }

                if (roiCtx) {
                    roiCtx.drawImage(video, cropX, cropY, cropW, cropH, 0, 0, targetRoiW, targetRoiH);
                    try {
                        const zxingResult = codeReader?.decodeFromCanvas(roiCanvas);
                        if (zxingResult) {
                            const text = zxingResult.getText();
                            if (text) {
                                await onScanResult(text);
                                isDecoding = false;
                                return;
                            }
                        }
                    } catch {
                        // Belum mendeteksi di frame ROI ini
                    }
                }

                // 3. Coba Full Frame berskala kecil (setiap 2 siklus)
                if (scanCycle % 2 === 0) {
                    const targetFullW = 560;
                    const targetFullH = Math.round((targetFullW / vw) * vh);
                    if (!fullCanvas) fullCanvas = document.createElement('canvas');
                    if (fullCanvas.width !== targetFullW || fullCanvas.height !== targetFullH) {
                        fullCanvas.width = targetFullW;
                        fullCanvas.height = targetFullH;
                        fullCtx = fullCanvas.getContext('2d', { willReadFrequently: true });
                    }
                    if (fullCtx) {
                        fullCtx.drawImage(video, 0, 0, targetFullW, targetFullH);
                        try {
                            const zxingResult = codeReader?.decodeFromCanvas(fullCanvas);
                            if (zxingResult) {
                                const text = zxingResult.getText();
                                if (text) {
                                    await onScanResult(text);
                                    isDecoding = false;
                                    return;
                                }
                            }
                        } catch {
                            // Belum mendeteksi di full frame
                        }
                    }
                }

                // 4. Coba Rotasi 90 derajat jika barcode vertikal (setiap 3 siklus)
                if (scanCycle % 3 === 0 && roiCanvas) {
                    if (!rotCanvas) rotCanvas = document.createElement('canvas');
                    if (rotCanvas.width !== roiCanvas.height || rotCanvas.height !== roiCanvas.width) {
                        rotCanvas.width = roiCanvas.height;
                        rotCanvas.height = roiCanvas.width;
                        rotCtx = rotCanvas.getContext('2d', { willReadFrequently: true });
                    }
                    if (rotCtx) {
                        rotCtx.save();
                        rotCtx.translate(rotCanvas.width / 2, rotCanvas.height / 2);
                        rotCtx.rotate(Math.PI / 2);
                        rotCtx.drawImage(roiCanvas, -roiCanvas.width / 2, -roiCanvas.height / 2);
                        rotCtx.restore();
                        try {
                            const zxingResult = codeReader?.decodeFromCanvas(rotCanvas);
                            if (zxingResult) {
                                const text = zxingResult.getText();
                                if (text) {
                                    await onScanResult(text);
                                    isDecoding = false;
                                    return;
                                }
                            }
                        } catch {
                            // Belum mendeteksi di rotasi
                        }
                    }
                }
            } catch {
                // NotFoundException normal terjadi saat tidak ada barcode di frame
            } finally {
                isDecoding = false;
            }
        }, 150);

        // Muat ulang daftar kamera jika nama kamera sebelumnya masih kosong
        if (daftarKamera.value.length <= 1 || !daftarKamera.value[0]?.label) {
            try {
                const devices = await listVideoDevices();
                daftarKamera.value = devices;
                if (!kameraDipilih.value && devices.length > 0) {
                    kameraDipilih.value = devices[0].deviceId;
                }
            } catch {
                // abaikan
            }
        }
    } catch (err: unknown) {
        scanLoading.value = false;
        tanganiErrorKamera(err);
    }
}

async function bukaScan() {
    showScan.value = true;
    scanError.value = '';
    scanLoading.value = true;
    pesanScanTerakhir.value = null;
    scanManualInput.value = '';
    scanSuccessPulse.value = false;
    isHandlingResult = false;
    barcodeTerakhirDiScan = '';
    waktuScanTerakhir = 0;

    await nextTick();

    if (!navigator?.mediaDevices?.getUserMedia) {
        scanLoading.value = false;
        scanError.value = 'Akses kamera memerlukan koneksi aman (HTTPS atau localhost). Anda masih dapat mengunggah foto barcode atau input manual di bawah.';
        return;
    }

    // Unduh pustaka ZXing lebih awal selagi menunggu izin kamera (penting di HP lemot).
    getCodeReader().catch(() => null);

    try {
        const devices = await listVideoDevices();
        daftarKamera.value = devices;

        if (!kameraDipilih.value && devices.length > 0) {
            const backCam = devices.find((d) =>
                /back|belakang|rear|environment/i.test(d.label)
            );
            kameraDipilih.value = backCam ? backCam.deviceId : devices[0].deviceId;
        }
    } catch (e) {
        console.warn('Gagal membaca daftar kamera:', e);
    }

    await mulaiKamera();
}

async function gantiKamera(deviceId: string) {
    kameraDipilih.value = deviceId;
    await mulaiKamera();
}

function hentikanScan() {
    scanAktif.value = false;
    scanLoading.value = false;
    if (scanLoopTimer) {
        clearInterval(scanLoopTimer);
        scanLoopTimer = undefined;
    }
    if (mediaStream) {
        try {
            mediaStream.getTracks().forEach((t) => t.stop());
        } catch (e) {
            console.warn('Error menghentikan track stream:', e);
        }
        mediaStream = null;
    }
    if (videoRef.value) {
        videoRef.value.srcObject = null;
    }
    torchOn.value = false;
    canTorch.value = false;
}

function tutupScan() {
    hentikanScan();
    showScan.value = false;
    pesanScanTerakhir.value = null;
    scanSuccessPulse.value = false;
    if (timerPesanScan) clearTimeout(timerPesanScan);
}

function tampilkanNotifScan(sukses: boolean, teks: string) {
    pesanScanTerakhir.value = { sukses, teks };
    if (timerPesanScan) clearTimeout(timerPesanScan);
    timerPesanScan = setTimeout(() => {
        pesanScanTerakhir.value = null;
    }, 3000);
}

// Decode gambar barcode dari file atau paste clipboard dengan multi-pass preprocessing
async function decodeImageElement(img: HTMLImageElement): Promise<string | null> {
    // 1. Coba Native BarcodeDetector terlebih dahulu jika didukung browser
    if (typeof window !== 'undefined' && 'BarcodeDetector' in window) {
        try {
            const supported: string[] = await (window as any).BarcodeDetector.getSupportedFormats();
            const wanted = ['upc_a', 'ean_13', 'ean_8', 'upc_e', 'code_128', 'code_39', 'code_93', 'itf', 'qr_code'];
            const valid = wanted.filter((f) => supported.includes(f));
            if (valid.length > 0) {
                const detector = new (window as any).BarcodeDetector({ formats: valid });
                const res = await detector.detect(img);
                if (res.length > 0 && res[0]?.rawValue) {
                    return res[0].rawValue;
                }
            }
        } catch {
            // lanjut ke ZXing
        }
    }

    const naturalW = img.naturalWidth || img.width;
    const naturalH = img.naturalHeight || img.height;
    if (!naturalW || !naturalH) return null;

    // Batasi resolusi maksimum ke 1200px agar ZXing memproses lebih cepat dan akurat
    const scale = Math.min(1, 1200 / Math.max(naturalW, naturalH));
    const targetW = Math.max(1, Math.floor(naturalW * scale));
    const targetH = Math.max(1, Math.floor(naturalH * scale));

    const canvas = document.createElement('canvas');
    canvas.width = targetW;
    canvas.height = targetH;
    const ctx = canvas.getContext('2d');
    if (!ctx) return null;
    ctx.drawImage(img, 0, 0, targetW, targetH);

    const tryDecodeCanvas = (cvs: HTMLCanvasElement): string | null => {
        try {
            const r = codeReader?.decodeFromCanvas(cvs);
            if (r && r.getText()) return r.getText();
        } catch {
            // lanjut coba berikutnya
        }
        return null;
    };

    // Pastikan ZXing sudah terunduh sebelum decode via canvas (Native Detector sudah dicoba di atas).
    if (!codeReader) {
        try {
            await getCodeReader();
        } catch {
            return null;
        }
    }

    // 2. Coba decode gambar original
    const directResult = tryDecodeCanvas(canvas);
    if (directResult) return directResult;

    // 3. Coba Crop Area Potensial Barcode (bawah 60% dan tengah 60%)
    // Foto kemasan produk makanan/minuman biasanya memiliki barcode di bagian bawah atau tengah
    const crops = [
        { x: 0, y: Math.floor(targetH * 0.35), w: targetW, h: Math.floor(targetH * 0.60) },
        { x: Math.floor(targetW * 0.05), y: Math.floor(targetH * 0.45), w: Math.floor(targetW * 0.90), h: Math.floor(targetH * 0.40) },
        { x: Math.floor(targetW * 0.10), y: Math.floor(targetH * 0.20), w: Math.floor(targetW * 0.80), h: Math.floor(targetH * 0.60) },
    ];

    for (const c of crops) {
        if (c.w <= 10 || c.h <= 10) continue;
        const cCanvas = document.createElement('canvas');
        cCanvas.width = c.w;
        cCanvas.height = c.h;
        const cCtx = cCanvas.getContext('2d');
        if (cCtx) {
            cCtx.drawImage(canvas, c.x, c.y, c.w, c.h, 0, 0, c.w, c.h);
            const cropResult = tryDecodeCanvas(cCanvas);
            if (cropResult) return cropResult;
        }
    }

    // 4. Peningkatan Kontras & Peregangan Histogram Luminance
    // Berguna untuk foto layar HP/laptop atau kemasan berkilau / bergelombang
    try {
        const enhancedCanvas = document.createElement('canvas');
        enhancedCanvas.width = targetW;
        enhancedCanvas.height = targetH;
        const eCtx = enhancedCanvas.getContext('2d');
        if (eCtx) {
            eCtx.drawImage(canvas, 0, 0);
            const imgData = eCtx.getImageData(0, 0, targetW, targetH);
            const d = imgData.data;
            let minL = 255;
            let maxL = 0;
            for (let i = 0; i < d.length; i += 4) {
                const lum = (d[i] * 0.299 + d[i + 1] * 0.587 + d[i + 2] * 0.114);
                if (lum < minL) minL = lum;
                if (lum > maxL) maxL = lum;
            }
            const range = (maxL - minL) || 1;
            for (let i = 0; i < d.length; i += 4) {
                const lum = (d[i] * 0.299 + d[i + 1] * 0.587 + d[i + 2] * 0.114);
                const stretched = Math.min(255, Math.max(0, ((lum - minL) / range) * 255));
                d[i] = stretched;
                d[i + 1] = stretched;
                d[i + 2] = stretched;
            }
            eCtx.putImageData(imgData, 0, 0);
            const contrastResult = tryDecodeCanvas(enhancedCanvas);
            if (contrastResult) return contrastResult;
        }
    } catch {
        // abaikan jika getImageData dicegah (CORS)
    }

    // 5. Coba Rotasi 90 derajat jika barcode berorientasi vertikal
    const rCanvas = document.createElement('canvas');
    rCanvas.width = targetH;
    rCanvas.height = targetW;
    const rCtx = rCanvas.getContext('2d');
    if (rCtx) {
        rCtx.save();
        rCtx.translate(rCanvas.width / 2, rCanvas.height / 2);
        rCtx.rotate(Math.PI / 2);
        rCtx.drawImage(canvas, -targetW / 2, -targetH / 2);
        rCtx.restore();
        const rResult = tryDecodeCanvas(rCanvas);
        if (rResult) return rResult;
    }

    return null;
}

async function handleImageFile(file: File) {
    scanLoading.value = true;
    try {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.src = url;
        await new Promise((resolve, reject) => {
            img.onload = resolve;
            img.onerror = reject;
        });
        const text = await decodeImageElement(img);
        URL.revokeObjectURL(url);

        if (text) {
            await onScanResult(text);
        } else {
            toast.error('Barcode tidak terdeteksi pada gambar ini. Pastikan gambar jelas dan tidak buram.');
        }
    } catch (err: any) {
        toast.error('Gagal membaca gambar: ' + (err?.message || 'Format tidak didukung'));
    } finally {
        scanLoading.value = false;
        if (fileInputRef.value) fileInputRef.value.value = '';
    }
}

function onFileChange(e: Event) {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) handleImageFile(file);
}

function onPasteWindow(e: ClipboardEvent) {
    if (!showScan.value) return;
    const items = e.clipboardData?.items;
    if (!items) return;
    for (const item of items) {
        if (item.type.startsWith('image/')) {
            const file = item.getAsFile();
            if (file) {
                e.preventDefault();
                handleImageFile(file);
                break;
            }
        }
    }
}

async function onScanResult(text: string) {
    const now = Date.now();
    // Cegah duplikasi scan barcode yang sama dalam kurun waktu 1.8 detik
    if (text === barcodeTerakhirDiScan && now - waktuScanTerakhir < 1800) {
        return;
    }
    barcodeTerakhirDiScan = text;
    waktuScanTerakhir = now;

    // Flash visual reticle
    scanSuccessPulse.value = true;
    if (scanSuccessPulseTimer) clearTimeout(scanSuccessPulseTimer);
    scanSuccessPulseTimer = setTimeout(() => {
        scanSuccessPulse.value = false;
    }, 700);

    if (!scanBeruntun.value) {
        tutupScan();
    }

    isHandlingResult = true;
    await hasilScan(text);
    isHandlingResult = false;
}

async function hasilScan(kode: string) {
    cartError.value = '';
    const kodeClean = kode.trim();
    if (!kodeClean) return;
    const rawKode = kodeClean.replace(/\s+/g, '');

    const matchBarcode = (b: string | null | undefined) => {
        if (!b) return false;
        const bClean = b.trim();
        const bRaw = bClean.replace(/\s+/g, '');
        if (bClean.toLowerCase() === kodeClean.toLowerCase() || bRaw.toLowerCase() === rawKode.toLowerCase()) return true;

        // Cocokkan tanpa angka nol di depan (misal: 089686598056 dengan 89686598056)
        const bNoZero = bRaw.replace(/^0+/, '');
        const rNoZero = rawKode.replace(/^0+/, '');
        if (bNoZero && rNoZero && bNoZero === rNoZero) return true;

        // Normalisasi UPC-A (12 digit) ke EAN-13 (13 digit diawali 0)
        if ('0' + bRaw === rawKode || '0' + rawKode === bRaw) return true;

        // Awalan cocok untuk barcode kemasan berlipat (misal 11 digit pertama cocok)
        if (bRaw.length >= 11 && rawKode.length >= 11) {
            if (bRaw.startsWith(rawKode) || rawKode.startsWith(bRaw)) return true;
        }

        return false;
    };

    // 1. Cek langsung di daftar produk lokal (katalog yang sedang aktif & props.produk_list)
    const localSources = [
        ...produkHasil.value,
        ...(props.produk_list ? props.produk_list : []),
    ];

    const cocokLokal = localSources.find((p) => matchBarcode(p.barcode));

    if (cocokLokal) {
        bunyiBeep();
        const berhasil = tambahProduk(cocokLokal);
        if (berhasil) {
            toast.success(`✓ ${cocokLokal.nama} ditambahkan ke keranjang`);
            tampilkanNotifScan(true, `✓ ${cocokLokal.nama} ditambahkan ke keranjang`);
        } else {
            toast.error(cartError.value || 'Gagal menambahkan produk ke keranjang');
            tampilkanNotifScan(false, `✗ ${cartError.value}`);
        }
        produkKeyword.value = '';
        scanManualInput.value = '';
        return;
    }

    // 2. Jika tidak ditemukan di data lokal, cari via API server
    // (termasuk pengenalan katalog publik bila barcode belum terdaftar).
    const toastKenali = toast.loading('Mengenali barcode...');
    try {
        const queryTerm = rawKode || kodeClean;
        const r = await fetch(`/kasir/produk?search=${encodeURIComponent(queryTerm)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const j = await r.json();
        toast.dismiss(toastKenali);
        const daftar: Produk[] = j.data ?? [];
        const kandidat: KandidatCepat | null = j.kandidat ?? null;

        const cocokServer = daftar.find((p) => matchBarcode(p.barcode)) || (daftar.length === 1 ? daftar[0] : undefined);

        if (cocokServer) {
            bunyiBeep();
            if (!produkHasil.value.some((p) => p.id_barang === cocokServer.id_barang)) {
                produkHasil.value.unshift(cocokServer);
            }
            const berhasil = tambahProduk(cocokServer);
            if (berhasil) {
                const infoBaru = cocokServer.baru ? ' (produk baru — pastikan harga benar)' : '';
                toast.success(`✓ ${cocokServer.nama} ditambahkan ke keranjang${infoBaru}`);
                tampilkanNotifScan(true, `✓ ${cocokServer.nama} ditambahkan ke keranjang${infoBaru}`);
            } else {
                toast.error(cartError.value || 'Gagal menambahkan produk ke keranjang');
                tampilkanNotifScan(false, `✗ ${cartError.value}`);
            }
            produkKeyword.value = '';
            scanManualInput.value = '';
        } else if (kandidat) {
            // Barcode belum terdaftar: tawarkan tambah cepat (nama dari katalog
            // publik bila dikenali, atau isi manual) lalu masuk keranjang.
            bukaTambahCepat(kandidat);
        } else {
            const pesan = `Barcode "${kodeClean}" tidak ditemukan di database produk.`;
            cartError.value = pesan;
            toast.error(pesan);
            tampilkanNotifScan(false, `✗ ${pesan}`);
        }
    } catch {
        toast.dismiss(toastKenali);
        const pesan = `Gagal mencari produk dengan barcode "${kodeClean}".`;
        cartError.value = pesan;
        toast.error(pesan);
        tampilkanNotifScan(false, `✗ ${pesan}`);
    }
}

async function onEnterCariProduk() {
    const inputEl = document.getElementById('cari-produk') as HTMLInputElement | null;
    const val = inputEl?.value?.trim() || produkKeyword.value.trim();
    if (!val) return;
    await hasilScan(val);
}

// ---------- Tambah cepat produk hasil scan (semua produk Indonesia) ----------
const showCepat = ref(false);
const cepatSumber = ref('');
const cepatBarcodeValid = ref(true);
const cepatProdukIndonesia = ref(false);
const cepatLoading = ref(false);
const cepatError = ref('');
const cepatForm = reactive({
    barcode: '',
    nama: '',
    id_kategori: '' as string | number,
    satuan: 'pcs',
    harga_jual: '' as string | number,
    stok: 1 as string | number,
});

function bukaTambahCepat(k: KandidatCepat) {
    cepatSumber.value = k.sumber === 'master'
        ? 'Dikenali dari katalog Indonesia'
        : (k.sumber ? 'Dikenali dari katalog publik' : '');
    cepatBarcodeValid.value = k.barcode_valid;
    cepatProdukIndonesia.value = k.produk_indonesia;
    cepatError.value = '';
    cepatForm.barcode = k.barcode;
    cepatForm.nama = k.nama ?? '';
    cepatForm.satuan = k.satuan || 'pcs';
    cepatForm.harga_jual = k.harga ?? '';
    cepatForm.stok = 1;
    // Cocokkan kategori kandidat ke daftar kategori sekolah bila ada yang mirip.
    cepatForm.id_kategori = '';
    const target = (k.kategori || '').toLowerCase();
    const cocok = (props.kategori_list ?? []).find((c) => {
        const nama = (c.nama || '').toLowerCase();
        return nama && (nama.includes(target) || target.includes(nama));
    });
    if (cocok) cepatForm.id_kategori = cocok.id_kategori;
    showCepat.value = true;
    bunyiBeep();
}

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function simpanCepat() {
    cepatError.value = '';
    if (!cepatForm.nama.trim()) {
        cepatError.value = 'Isi nama produk terlebih dahulu.';
        return;
    }
    const harga = Number(cepatForm.harga_jual);
    if (!cepatForm.harga_jual || isNaN(harga) || harga < 1) {
        cepatError.value = 'Isi harga jual yang benar (minimal Rp 1).';
        return;
    }
    const stok = Math.max(1, Math.floor(Number(cepatForm.stok) || 1));
    cepatLoading.value = true;
    try {
        const r = await fetch('/kasir/produk-cepat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                barcode: cepatForm.barcode,
                nama: cepatForm.nama.trim(),
                id_kategori: cepatForm.id_kategori === '' ? null : Number(cepatForm.id_kategori),
                satuan: cepatForm.satuan.trim() || 'pcs',
                harga_jual: harga,
                stok,
            }),
        });
        const j = await r.json();
        if (!r.ok) {
            const errs = j.errors as Record<string, string[]> | undefined;
            const pertama = errs ? Object.values(errs)[0]?.[0] : undefined;
            throw new Error(pertama || j.message || 'Gagal menyimpan produk.');
        }
        const produk: Produk = j.data;
        // Masukkan ke katalog lokal agar scan berikutnya langsung cocok.
        produkHasil.value.unshift(produk);
        showCepat.value = false;
        const berhasil = tambahProduk(produk);
        if (berhasil) {
            toast.success(`✓ ${produk.nama} ditambahkan ke keranjang`);
            tampilkanNotifScan(true, `✓ ${produk.nama} ditambahkan ke keranjang`);
        } else {
            toast.error(cartError.value || 'Produk tersimpan, tetapi gagal masuk keranjang.');
        }
        produkKeyword.value = '';
        scanManualInput.value = '';
    } catch (e) {
        cepatError.value = e instanceof Error ? e.message : 'Gagal menyimpan produk.';
    } finally {
        cepatLoading.value = false;
    }
}

// Shortcut keyboard & Penangan Alat Barcode Scanner Fisik
function tombolCepat(e: KeyboardEvent) {
    if (showBayar.value || showStruk.value) {
        if (e.key === 'Escape') {
            showBayar.value = false;
            showStruk.value = false;
        }
        return;
    }

    if (e.key === 'Escape') {
        if (showScan.value) tutupScan();
        return;
    }
    if (e.key === 'F2') {
        e.preventDefault();
        if (!showBayar.value && !showScan.value) bukaBayar();
        return;
    }
    if (e.key === 'F3') {
        e.preventDefault();
        if (showScan.value) tutupScan();
        else bukaScan();
        return;
    }
    if (e.key === 'F4') {
        e.preventDefault();
        if (!showBayar.value && !showScan.value) parkirCepat();
        return;
    }

    const target = e.target as HTMLElement | null;
    const isSearchInput = target?.id === 'cari-produk';
    const isOtherInput = target && (target.tagName === 'INPUT' || target.tagName === 'SELECT' || target.tagName === 'TEXTAREA') && !isSearchInput;

    const now = Date.now();
    const interval = now - barcodeScannerLastTime;
    barcodeScannerLastTime = now;

    // Deteksi Enter dari scanner barcode fisik atau pengetikan di input cari
    if (e.key === 'Enter' || (e.key === 'Tab' && barcodeScannerBuffer.length >= 3)) {
        if (isSearchInput) {
            e.preventDefault();
            onEnterCariProduk();
            barcodeScannerBuffer = '';
            return;
        }
        if (barcodeScannerBuffer.length >= 3) {
            e.preventDefault();
            const scannedCode = barcodeScannerBuffer.trim();
            barcodeScannerBuffer = '';
            hasilScan(scannedCode);
            return;
        }
        barcodeScannerBuffer = '';
        return;
    }

    if (e.key === '/' && !target?.matches?.('input, select, textarea') && !showScan.value) {
        e.preventDefault();
        document.getElementById('cari-produk')?.focus();
        return;
    }

    // Tangkap ketikan beruntun scanner barcode hardware (< 80ms)
    if (e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey && !isOtherInput) {
        if (interval < 80) {
            barcodeScannerBuffer += e.key;
        } else {
            barcodeScannerBuffer = e.key;
        }
        clearTimeout(barcodeScannerTimer);
        barcodeScannerTimer = setTimeout(() => {
            barcodeScannerBuffer = '';
        }, 250);
    }
}

const formatRp = (n: number) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');

const formatBarcode = (code?: string | null): string => {
    if (!code) return '';
    const clean = String(code).replace(/\s+/g, '');
    if (/^\d{13}$/.test(clean)) {
        return `${clean.slice(0, 1)} ${clean.slice(1, 7)} ${clean.slice(7)}`;
    }
    return String(code);
};

type StrukItem = { nama: string | null; qty: number; harga: number; diskon: number; subtotal: number };
type Struk = {
    id_penjualan: number; tanggal: string; sekolah?: string | null;
    alamat_sekolah?: string | null; kasir?: string | null; pelanggan?: string | null;
    jenis_transaksi?: string | null; cara_bayar?: string | null;
    status_pembayaran?: string | null; note?: string | null;
    total_faktur: number; total_bayar: number; kembalian: number;
    total_diskon: number; item_count: number; items: StrukItem[];
};

const receipt = computed(() => flash.value.receipt as unknown as Struk | undefined);
const successMsg = computed(() => flash.value.success as string | undefined);

// ---------- Struk & cetak ----------
const showStruk = ref(false);
const strukId = ref<number | null>(null);

watch(
    receipt,
    (r) => {
        if (r && r.id_penjualan !== strukId.value) {
            strukId.value = r.id_penjualan;
            showStruk.value = true;
        }
    },
    { immediate: true },
);

function cetakStruk() {
    window.print();
}
</script>

<template>
    <Head title="Kasir / Transaksi" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 pb-28 md:p-6 xl:pb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-emerald-900">
                    Kasir / Transaksi
                </h1>
                <p class="mt-0.5 text-sm text-neutral-500">
                    Buat transaksi penjualan baru dengan desain animasi modern & glassmorphism
                    <span v-if="sekolah?.nama_sekolah" class="font-medium text-neutral-700">
                        — {{ sekolah.nama_sekolah }}
                    </span>
                </p>
            </div>
        </div>

        <!-- Notifikasi -->
        <div
            v-if="successMsg"
            class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800"
        >
            {{ successMsg }}
            <span v-if="receipt" class="mt-1 block font-normal text-green-700">
                Struk #{{ receipt.id_penjualan }} — {{ receipt.item_count }} item —
                Total {{ formatRp(receipt.total_faktur) }} — Bayar
                {{ formatRp(receipt.total_bayar) }} — Kembalian
                {{ formatRp(receipt.kembalian) }}
            </span>
        </div>
        <div
            v-if="formErrors.items"
            class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700"
        >
            {{ formErrors.items }}
        </div>

        <!-- TRANSAKSI -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
            <!-- Kiri: Katalog Produk (Pilih Produk) -->
            <div class="flex flex-col gap-4 lg:col-span-7 xl:col-span-7 2xl:col-span-8">
                <!-- KATALOG PRODUK LANGSUNG TERLIHAT -->
                <div class="flex h-full flex-col rounded-2xl border border-white/60 bg-white/80 p-5 shadow-[0_8px_30px_rgb(0,0,0,0.05)] backdrop-blur-xl">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <h2 class="flex items-center gap-1.5 text-sm font-bold text-emerald-800">
                                <Package class="h-4 w-4 text-emerald-600" />
                                <span>Pilih Produk</span>
                            </h2>
                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                                {{ produkHasil.length }} produk
                            </span>
                        </div>
                        <!-- Switcher Grid / List -->
                        <div class="flex items-center gap-1 rounded-lg border border-neutral-200 bg-neutral-50 p-0.5">
                            <button
                                type="button"
                                class="flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium transition"
                                :class="viewMode === 'grid' ? 'bg-white font-semibold text-emerald-700 shadow-xs' : 'text-neutral-500 hover:text-neutral-800'"
                                @click="viewMode = 'grid'"
                                title="Tampilan Grid Kartu"
                            >
                                <LayoutGrid class="h-3.5 w-3.5" />
                                <span class="hidden sm:inline">Grid</span>
                            </button>
                            <button
                                type="button"
                                class="flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium transition"
                                :class="viewMode === 'list' ? 'bg-white font-semibold text-emerald-700 shadow-xs' : 'text-neutral-500 hover:text-neutral-800'"
                                @click="viewMode = 'list'"
                                title="Tampilan Daftar Ringkas"
                            >
                                <List class="h-3.5 w-3.5" />
                                <span class="hidden sm:inline">List</span>
                            </button>
                        </div>
                    </div>

                    <!-- Kolom Pencarian & Scan Barcode (sticky di mobile) -->
                    <div class="sticky top-0 z-10 -mx-1 bg-white/80 px-1 pt-1 pb-2 backdrop-blur-xl">
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-emerald-600" />
                            <Input
                                id="cari-produk"
                                v-model="produkKeyword"
                                @keydown.enter.prevent="onEnterCariProduk"
                                placeholder="Cari barcode / nama produk... (tekan / atau tembak scanner)"
                                class="pl-9 pr-8"
                                autocomplete="off"
                            />
                            <button
                                v-if="produkKeyword"
                                type="button"
                                class="absolute top-1/2 right-2.5 -translate-y-1/2 text-neutral-400 hover:text-neutral-600"
                                @click="produkKeyword = ''"
                                title="Bersihkan pencarian"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                        <Button type="button" variant="outline" class="shrink-0 border-emerald-200 text-emerald-700 hover:bg-emerald-50" @click="bukaScan()" title="Scan barcode dengan kamera (F3)">
                            <ScanBarcode class="h-4 w-4" />
                            <span class="hidden sm:inline">Scan (F3)</span>
                        </Button>
                    </div>

                    <!-- Filter Kategori (Pills) -->
                    <div v-if="kategori_list && kategori_list.length > 0" class="mt-2.5 flex items-center gap-1.5 overflow-x-auto pb-1 text-xs">
                        <button
                            type="button"
                            class="min-h-9 shrink-0 rounded-full px-4 py-2 text-xs font-semibold transition"
                            :class="kategoriDipilih === null ? 'bg-emerald-700 text-white shadow-xs' : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200'"
                            @click="kategoriDipilih = null"
                        >
                            Semua
                        </button>
                        <button
                            v-for="k in kategori_list"
                            :key="k.id_kategori"
                            type="button"
                            class="min-h-9 shrink-0 rounded-full px-4 py-2 text-xs font-medium transition"
                            :class="kategoriDipilih === k.id_kategori ? 'bg-emerald-700 font-semibold text-white shadow-xs' : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200'"
                            @click="kategoriDipilih = k.id_kategori"
                        >
                            {{ k.nama }}
                        </button>
                    </div>
                    </div>

                    <!-- DAFTAR / GRID PRODUK LANGSUNG TERLIHAT -->
                    <div class="mt-3 max-h-[calc(100vh-230px)] min-h-[520px] flex-1 overflow-y-auto pr-1">
                        <!-- Loading Skeleton -->
                        <div v-if="produkLoading" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 xl:grid-cols-4 gap-2.5">
                            <div v-for="n in 8" :key="n" class="space-y-2 rounded-xl border border-neutral-100 p-2">
                                <Skeleton class="h-20 w-full rounded-lg" />
                                <Skeleton class="h-3 w-3/4" />
                                <Skeleton class="h-4 w-1/2" />
                            </div>
                        </div>

                        <!-- GRID VIEW -->
                        <div
                            v-else-if="produkHasil.length > 0 && viewMode === 'grid'"
                            class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 xl:grid-cols-4 gap-2.5"
                        >
                            <button
                                v-for="p in produkHasil"
                                :key="p.id_barang"
                                type="button"
                                :disabled="p.stok <= 0"
                                class="group relative flex flex-col justify-between overflow-hidden rounded-xl border p-2.5 text-left transition"
                                :class="[
                                    p.stok <= 0
                                        ? 'border-neutral-200 bg-neutral-50/70 opacity-60 cursor-not-allowed'
                                        : 'border-neutral-200 bg-white hover:border-emerald-500 hover:shadow-md active:scale-[0.98] cursor-pointer'
                                ]"
                                @click="tambahProduk(p)"
                            >
                                <!-- Indikator jumlah di keranjang -->
                                <span
                                    v-if="qtyDiKeranjang(p.id_barang) > 0"
                                    class="absolute top-2 right-2 z-10 flex items-center gap-0.5 rounded-full bg-emerald-600 px-2 py-0.5 text-[11px] font-bold text-white shadow-xs ring-2 ring-white"
                                >
                                    ✓ {{ qtyDiKeranjang(p.id_barang) }}
                                </span>

                                <!-- Foto Produk atau Placeholder -->
                                <div class="relative mb-2 flex h-24 w-full items-center justify-center overflow-hidden rounded-lg bg-neutral-100">
                                    <img
                                        v-if="p.foto_url"
                                        :src="p.foto_url"
                                        :alt="p.nama ?? ''"
                                        class="h-full w-full object-cover transition duration-200 group-hover:scale-105"
                                        loading="lazy"
                                    />
                                    <ShoppingBag v-else class="h-8 w-8 text-neutral-300" />

                                    <!-- Badge Stok di atas foto -->
                                    <span
                                        v-if="p.stok <= 0"
                                        class="absolute bottom-1.5 left-1.5 rounded bg-rose-600/90 px-1.5 py-0.5 text-[10px] font-bold text-white shadow-xs"
                                    >
                                        Habis
                                    </span>
                                    <span
                                        v-else-if="p.stok <= 5"
                                        class="absolute bottom-1.5 left-1.5 rounded bg-amber-600/90 px-1.5 py-0.5 text-[10px] font-bold text-white shadow-xs"
                                    >
                                        Sisa {{ p.stok }}
                                    </span>
                                    <span
                                        v-else
                                        class="absolute bottom-1.5 left-1.5 rounded bg-neutral-900/60 px-1.5 py-0.5 text-[10px] font-medium text-white backdrop-blur-xs"
                                    >
                                        Stok {{ p.stok }}
                                    </span>
                                </div>

                                <!-- Detail Produk -->
                                <div class="flex flex-1 flex-col justify-between">
                                    <div>
                                        <span class="block line-clamp-2 text-xs font-semibold leading-tight text-neutral-800 group-hover:text-emerald-700">
                                            {{ p.nama }}
                                        </span>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between border-t border-neutral-100 pt-1.5">
                                        <span class="text-xs font-bold text-emerald-700">
                                            {{ formatRp(p.harga_jual) }}
                                        </span>
                                        <span
                                            v-if="p.stok > 0"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-emerald-50 text-emerald-700 transition group-hover:bg-emerald-600 group-hover:text-white"
                                            title="Tambah ke keranjang"
                                        >
                                            <Plus class="h-3.5 w-3.5" />
                                        </span>
                                    </div>
                                </div>
                            </button>
                        </div>

                        <!-- LIST VIEW -->
                        <div
                            v-else-if="produkHasil.length > 0 && viewMode === 'list'"
                            class="divide-y divide-neutral-100 overflow-hidden rounded-lg border border-neutral-200"
                        >
                            <button
                                v-for="p in produkHasil"
                                :key="p.id_barang"
                                type="button"
                                :disabled="p.stok <= 0"
                                class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm transition"
                                :class="[
                                    p.stok <= 0
                                        ? 'bg-neutral-50/70 opacity-60 cursor-not-allowed'
                                        : 'hover:bg-neutral-50 active:bg-emerald-50/50'
                                ]"
                                @click="tambahProduk(p)"
                            >
                                <div class="relative flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-neutral-200 bg-neutral-100">
                                    <img v-if="p.foto_url" :src="p.foto_url" :alt="p.nama ?? ''" class="h-full w-full object-cover" loading="lazy" />
                                    <ShoppingBag v-else class="h-5 w-5 text-neutral-300" />
                                    <span
                                        v-if="qtyDiKeranjang(p.id_barang) > 0"
                                        class="absolute inset-0 flex items-center justify-center bg-emerald-700/80 text-[11px] font-bold text-white backdrop-blur-xs"
                                    >
                                        ✓{{ qtyDiKeranjang(p.id_barang) }}
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <span class="block truncate font-medium text-neutral-900">{{ p.nama }}</span>
                                    <div class="mt-0.5 flex items-center gap-3">
                                        <span v-if="p.barcode" class="text-[11px] text-neutral-400 font-mono tracking-tight">{{ formatBarcode(p.barcode) }}</span>
                                        <span v-else class="text-[11px] text-neutral-300 font-mono">-</span>

                                        <span class="text-xs" :class="p.stok <= 0 ? 'text-rose-600 font-bold' : p.stok <= 5 ? 'text-amber-600 font-medium' : 'text-neutral-500'">
                                            {{ p.stok <= 0 ? 'Stok Habis' : `Stok: ${p.stok}` }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <span class="text-sm font-semibold text-emerald-800">
                                        {{ formatRp(p.harga_jual) }}
                                    </span>
                                    <span
                                        v-if="p.stok > 0"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-emerald-50 text-emerald-700 transition hover:bg-emerald-600 hover:text-white"
                                    >
                                        <Plus class="h-4 w-4" />
                                    </span>
                                </div>
                            </button>
                        </div>

                        <!-- EMPTY STATE -->
                        <div v-else class="py-8 text-center text-sm text-neutral-400">
                            <Package class="mx-auto mb-2 h-10 w-10 text-neutral-300" />
                            <p class="font-medium text-neutral-600">Tidak ada produk ditemukan</p>
                            <p v-if="produkKeyword || kategoriDipilih" class="mt-1 text-xs text-neutral-400">
                                Coba kata kunci lain atau bersihkan filter pencarian.
                            </p>
                            <button
                                v-if="produkKeyword || kategoriDipilih"
                                type="button"
                                class="mt-3 inline-flex items-center gap-1 rounded-md bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100"
                                @click="resetFilterProduk"
                            >
                                Reset Filter
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kanan: Panel Transaksi (Keranjang Belanja, Pelanggan, Total & Bayar) -->
            <div class="flex flex-col gap-4 lg:col-span-5 xl:col-span-5 2xl:col-span-4">
                <!-- KERANJANG BELANJA -->
                <div class="rounded-2xl border border-white/60 bg-white/80 p-5 shadow-[0_8px_30px_rgb(0,0,0,0.05)] backdrop-blur-xl">
                    <!-- TAB MULTI-KERANJANG (PARKIR ANTREAN) -->
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-1.5 border-b border-neutral-100 pb-3">
                        <div class="flex items-center gap-1 overflow-x-auto">
                            <button
                                v-for="s in slots"
                                :key="s.id"
                                type="button"
                                class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition"
                                :class="
                                    s.id === activeSlotId
                                        ? 'bg-emerald-700 text-white shadow-xs'
                                        : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200'
                                "
                                @click="gantiSlot(s.id)"
                            >
                                <span>{{ s.nama }}</span>
                                <span
                                    class="rounded-full px-1.5 py-0.2 text-[10px] font-bold"
                                    :class="
                                        s.id === activeSlotId
                                            ? 'bg-white/20 text-white'
                                            : s.cart.length > 0
                                              ? 'bg-amber-100 text-amber-800'
                                              : 'bg-neutral-200 text-neutral-600'
                                    "
                                >
                                    {{ s.cart.length > 0 ? `${s.cart.length} item` : '0' }}
                                </span>
                            </button>
                        </div>
                        <button
                            type="button"
                            class="flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 transition"
                            @click="parkirCepat"
                            title="Parkir antrean saat ini & buka antrean berikutnya (Shortcut: F4)"
                        >
                            <span>Parkir</span>
                            <kbd class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-mono">F4</kbd>
                        </button>
                    </div>

                    <!-- HEADER KERANJANG -->
                    <div class="flex items-center justify-between">
                        <h2 class="flex items-center gap-2 text-sm font-bold text-emerald-800">
                            <ReceiptText class="h-4 w-4" /> Keranjang Belanja
                            <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-semibold text-neutral-600">
                                {{ cart.length }} jenis ({{ cart.reduce((s, c) => s + c.qty, 0) }} pcs)
                            </span>
                        </h2>
                        <button
                            v-if="cart.length > 0"
                            type="button"
                            class="text-xs font-medium text-red-500 hover:text-red-700 hover:underline"
                            @click="konfirmasiBersihkan"
                        >
                            Kosongkan
                        </button>
                    </div>

                    <!-- BANNER UNDO HAPUS 5 DETIK -->
                    <div
                        v-if="itemDihapus"
                        class="mt-2.5 flex items-center justify-between gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 shadow-xs animate-in fade-in duration-200"
                    >
                        <div class="flex items-center gap-2 truncate">
                            <span>🗑️</span>
                            <span class="truncate font-medium">
                                "{{ itemDihapus.item.nama }}" ({{ itemDihapus.item.qty }} pcs) dihapus.
                            </span>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button
                                type="button"
                                class="rounded-md bg-amber-600 px-2.5 py-1 text-xs font-bold text-white hover:bg-amber-700 active:scale-95 transition"
                                @click="urungkanHapus"
                            >
                                Urungkan ({{ itemDihapus.countdown }}s)
                            </button>
                            <button
                                type="button"
                                class="text-amber-500 hover:text-amber-800"
                                @click="bersihkanTimerUndo"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- ERROR KERANJANG -->
                    <div v-if="cartError" class="mt-2 text-sm font-medium text-red-600">
                        {{ cartError }}
                    </div>

                    <!-- DAFTAR ITEM KERANJANG -->
                    <div v-if="cart.length === 0" class="mt-3 rounded-lg bg-neutral-50 px-4 py-8 text-center text-sm text-neutral-400">
                        Keranjang kosong. Klik produk di sebelah kiri untuk menambahkan.
                    </div>
                    <div v-else class="mt-3 max-h-[360px] 2xl:max-h-[420px] overflow-y-auto divide-y divide-neutral-100 pr-1">
                        <div
                            v-for="(c, i) in cart"
                            :key="c.id_barang"
                            class="py-3 first:pt-0 last:pb-0 space-y-1.5"
                        >
                            <!-- Info Produk + Tombol Hapus -->
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-start gap-2.5 min-w-0 flex-1">
                                    <img
                                        v-if="c.foto_url"
                                        :src="c.foto_url"
                                        :alt="c.nama ?? ''"
                                        class="h-10 w-10 shrink-0 rounded-lg border border-neutral-200 object-cover"
                                        loading="lazy"
                                    />
                                    <div
                                        v-else
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-neutral-200 bg-neutral-100 text-neutral-400"
                                    >
                                        <ShoppingBag class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <span class="block truncate text-xs font-semibold text-neutral-900 leading-snug">
                                            {{ c.nama }}
                                        </span>
                                        <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11px] text-neutral-500">
                                            <span class="font-medium text-emerald-700">{{ formatRp(c.harga_jual) }}</span>
                                            <span v-if="c.barcode" class="font-mono text-[10px] text-neutral-400">· {{ c.barcode }}</span>
                                            <!-- Sisa Stok Live -->
                                            <span
                                                v-if="c.stok - c.qty > 10"
                                                class="inline-flex items-center rounded bg-emerald-50 px-1.5 py-0.2 text-[10px] font-medium text-emerald-700"
                                            >
                                                Sisa: {{ c.stok - c.qty }}
                                            </span>
                                            <span
                                                v-else-if="c.stok - c.qty > 0"
                                                class="inline-flex items-center rounded bg-amber-50 px-1.5 py-0.2 text-[10px] font-semibold text-amber-700"
                                            >
                                                ⚠️ Sisa: {{ c.stok - c.qty }}
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center rounded bg-rose-50 px-1.5 py-0.2 text-[10px] font-bold text-rose-700"
                                            >
                                                ⛔ Habis
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-neutral-400 hover:bg-red-50 hover:text-red-600 transition active:scale-95"
                                    title="Hapus barang ini"
                                    @click="hapusItem(c.id_barang)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>

                            <!-- Controls: Qty Stepper, Subtotal, Diskon -->
                            <div class="flex flex-wrap items-center justify-between gap-2 pt-1 border-t border-neutral-50">
                                <div class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        class="flex h-7 w-7 items-center justify-center rounded-md border border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100 active:scale-95 transition"
                                        title="Kurangi 1"
                                        @click="ubahQty(c, -1)"
                                    >
                                        <Minus class="h-3 w-3" />
                                    </button>
                                    <input
                                        type="number"
                                        min="1"
                                        :max="c.stok"
                                        v-model.number="c.qty"
                                        @input="onKetikQty(c)"
                                        @blur="onBlurQty(c)"
                                        class="h-7 w-12 text-center rounded-md border border-teal-300 bg-teal-50/60 px-1 text-xs font-bold text-teal-800 focus:bg-white focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500 tabular-nums"
                                    />
                                    <button
                                        type="button"
                                        class="flex h-7 w-7 items-center justify-center rounded-md border border-teal-300 bg-teal-600 text-white hover:bg-teal-700 active:scale-95 transition"
                                        title="Tambah 1"
                                        @click="ubahQty(c, 1)"
                                    >
                                        <Plus class="h-3 w-3" />
                                    </button>
                                </div>

                                <div class="text-right">
                                    <span class="text-xs font-bold text-neutral-900 tabular-nums">
                                        {{ formatRp(c.harga_jual * c.qty - lineDiskon(c)) }}
                                    </span>
                                    <span v-if="lineDiskon(c) > 0" class="block text-[10px] text-green-600 font-normal">
                                        −{{ formatRp(lineDiskon(c)) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Diskon Input (opsional) -->
                            <div class="flex items-center gap-1.5 pt-0.5">
                                <select
                                    v-model="c.diskon_tipe"
                                    class="rounded border border-neutral-200 bg-neutral-50 px-1.5 py-0.5 text-[11px] text-neutral-700 focus:border-emerald-500 focus:outline-none"
                                >
                                    <option value="">Diskon</option>
                                    <option value="persen">Persen %</option>
                                    <option value="nominal">Nominal Rp</option>
                                </select>
                                <input
                                    v-if="c.diskon_tipe !== ''"
                                    v-model.number="c.diskon_nilai"
                                    type="number"
                                    min="0"
                                    class="w-20 rounded border border-neutral-200 bg-white px-1.5 py-0.5 text-[11px] text-neutral-800 focus:border-emerald-500 focus:outline-none"
                                    :placeholder="c.diskon_tipe === 'persen' ? '%' : 'Rp'"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PELANGGAN -->
                <div class="rounded-2xl border border-white/60 bg-white/80 p-5 shadow-[0_8px_30px_rgb(0,0,0,0.05)] backdrop-blur-xl">
                    <h2 class="flex items-center gap-2 text-sm font-bold text-emerald-800">
                        <UserIcon class="h-4 w-4" /> Pelanggan (Opsional)
                    </h2>
                    <div v-if="!pelangganDipilih" class="relative mt-2">
                        <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-emerald-600" />
                        <Input v-model="pelangganKeyword" placeholder="Cari pelanggan..." class="pl-9" autocomplete="off" />
                        <div v-if="pelangganHasil.length > 0" class="absolute z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-neutral-200 bg-white shadow-lg">
                            <button
                                v-for="p in pelangganHasil"
                                :key="p.id_pelanggan"
                                type="button"
                                class="block w-full px-3 py-2 text-left text-sm hover:bg-neutral-50"
                                @click="pilihPelanggan(p)"
                            >
                                <span class="block font-medium text-neutral-900">{{ p.nama_pelanggan }}</span>
                                <span class="block text-xs text-neutral-400">{{ p.telepon ?? '-' }}</span>
                            </button>
                        </div>
                    </div>
                    <div v-else class="mt-2 rounded-lg bg-neutral-50 p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 text-sm">
                                <p class="truncate font-semibold text-neutral-900">{{ pelangganDipilih.nama_pelanggan }}</p>
                                <p class="text-neutral-500">{{ pelangganDipilih.telepon ?? '-' }}</p>
                                <p class="mt-1 text-xs text-neutral-400">{{ pelangganDipilih.alamat ?? '-' }}</p>
                            </div>
                            <button type="button" class="text-neutral-400 hover:text-emerald-700" @click="pelangganDipilih = null">
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- RINGKASAN PEMBAYARAN & BAYAR -->
                <div class="rounded-2xl border border-white/60 bg-white/80 p-5 shadow-[0_8px_30px_rgb(0,0,0,0.05)] backdrop-blur-xl">
                    <div class="space-y-1.5 text-sm">
                        <div class="flex justify-between text-neutral-500">
                            <span>Subtotal</span><span>{{ formatRp(subtotal) }}</span>
                        </div>
                        <div class="flex justify-between text-neutral-500">
                            <span>Diskon</span><span>−{{ formatRp(totalDiskon) }}</span>
                        </div>
                        <div class="flex justify-between border-t border-neutral-100 pt-2 text-base font-bold text-emerald-800">
                            <span>Total</span><span>{{ formatRp(total) }}</span>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <Button type="button" variant="outline" @click="konfirmasiBersihkan">Bersihkan</Button>
                        <Button type="button" class="bg-emerald-600 hover:bg-emerald-700" :disabled="cart.length === 0" @click="bukaBayar" title="Shortcut: F2">
                            <Banknote class="mr-1 h-4 w-4" /> Bayar
                            <kbd class="ml-1 rounded bg-white/20 px-1.5 py-0.5 text-[10px] font-semibold">F2</kbd>
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile bar: total + bayar sticky di atas bottom nav, hanya di < lg -->
        <div class="fixed inset-x-0 bottom-[calc(62px+env(safe-area-inset-bottom))] z-30 border-t border-neutral-200 bg-white/95 px-4 pt-2 pb-3 shadow-[0_-4px_16px_rgba(0,0,0,0.08)] backdrop-blur lg:hidden">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] leading-none text-neutral-500">
                        {{ cart.length }} item · {{ cart.reduce((s, c) => s + c.qty, 0) }} pcs
                    </p>
                    <p class="mt-1 truncate text-lg leading-none font-bold text-emerald-800">
                        {{ formatRp(total) }}
                    </p>
                </div>
                <div class="flex shrink-0 gap-2">
                    <Button type="button" variant="outline" class="h-11 px-4" @click="konfirmasiBersihkan" :disabled="cart.length === 0">
                        Bersihkan
                    </Button>
                    <Button type="button" class="h-11 bg-emerald-600 px-6 text-base hover:bg-emerald-700" :disabled="cart.length === 0" @click="bukaBayar">
                        <Banknote class="mr-1 h-4 w-4" /> Bayar
                    </Button>
                </div>
            </div>
        </div>

    </div>

    <!-- Modal tambah cepat: barcode belum terdaftar -->
    <div v-if="showCepat" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/30 sm:items-center sm:p-4" @click.self="showCepat = false">
        <div class="max-h-[92vh] w-full overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:max-w-md sm:rounded-2xl">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-emerald-800">Produk Baru dari Scan</h3>
                <button type="button" class="flex h-9 w-9 items-center justify-center rounded-full text-neutral-400 hover:text-emerald-700" @click="showCepat = false">
                    <X class="h-5 w-5" />
                </button>
            </div>
            <p class="mt-1 font-mono text-xs text-neutral-500">Barcode: {{ cepatForm.barcode }}</p>
            <div class="mt-2 flex flex-wrap gap-1.5">
                <span v-if="cepatSumber" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">{{ cepatSumber }}</span>
                <span v-if="cepatProdukIndonesia" class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">Produk Indonesia (899)</span>
                <span v-if="!cepatBarcodeValid" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">Digit cek tidak valid — periksa hasil pindai</span>
            </div>

            <div class="mt-3 space-y-3">
                <div>
                    <Label>Nama Produk</Label>
                    <Input v-model="cepatForm.nama" class="mt-1.5" placeholder="cth: Teh Botol Sosro 350ml" autocomplete="off" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label>Harga Jual (Rp)</Label>
                        <Input v-model="cepatForm.harga_jual" type="number" min="1" inputmode="numeric" class="mt-1.5" placeholder="cth: 5000" />
                    </div>
                    <div>
                        <Label>Stok Awal</Label>
                        <Input v-model="cepatForm.stok" type="number" min="1" inputmode="numeric" class="mt-1.5" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label>Kategori</Label>
                        <select v-model="cepatForm.id_kategori" class="mt-1.5 h-9 w-full rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20">
                            <option value="">Tanpa kategori</option>
                            <option v-for="k in kategori_list" :key="k.id_kategori" :value="k.id_kategori">{{ k.nama }}</option>
                        </select>
                    </div>
                    <div>
                        <Label>Satuan</Label>
                        <Input v-model="cepatForm.satuan" class="mt-1.5" placeholder="pcs" autocomplete="off" />
                    </div>
                </div>
            </div>
            <p v-if="cepatError" class="mt-2 text-sm font-medium text-red-600">{{ cepatError }}</p>
            <div class="mt-4 grid grid-cols-2 gap-2">
                <Button type="button" variant="outline" class="h-11" :disabled="cepatLoading" @click="showCepat = false">Batal</Button>
                <Button type="button" class="h-11 bg-emerald-600 hover:bg-emerald-700" :disabled="cepatLoading" @click="simpanCepat()">{{ cepatLoading ? 'Menyimpan...' : 'Simpan & Tambah' }}</Button>
            </div>
        </div>
    </div>

    <!-- Modal pembayaran -->
    <div v-if="showBayar" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 p-4" @click.self="showBayar = false">
        <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-emerald-800">Pembayaran</h3>
                <button type="button" class="text-neutral-400 hover:text-emerald-700" @click="showBayar = false">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3">
                <div>
                    <Label>Jenis Transaksi</Label>
                    <select v-model="bayar.jenis_transaksi" class="mt-1.5 w-full h-9 rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20">
                        <option value="tunai">Tunai</option>
                        <option value="kredit">Kredit</option>
                    </select>
                </div>
                <div>
                    <Label>Cara Bayar</Label>
                    <select v-model="bayar.cara_bayar" class="mt-1.5 w-full h-9 rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20">
                        <option value="cash">Cash</option>
                        <option value="transfer">Transfer</option>
                        <option value="qris">QRIS</option>
                        <option value="debit">Debit</option>
                    </select>
                </div>
            </div>

            <div class="mt-3">
                <Label>Status Pembayaran</Label>
                <select v-model="bayar.status_pembayaran" class="mt-1.5 w-full h-9 rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20">
                    <option value="sudah bayar">Sudah bayar</option>
                    <option value="belum bayar">Belum bayar</option>
                </select>
            </div>

            <div class="mt-3 rounded-lg bg-neutral-50 p-3 text-sm">
                <div class="flex justify-between text-neutral-500"><span>Total</span><span class="font-bold text-neutral-900">{{ formatRp(total) }}</span></div>
            </div>

            <div class="mt-3">
                <Label for="total-bayar">Total Pembayaran</Label>
                <Input id="total-bayar" v-model.number="bayar.total_bayar" type="number" min="0" class="mt-1.5" />
            </div>

            <div class="mt-2 flex justify-between text-sm">
                <span class="text-neutral-500">Kembalian</span>
                <span class="font-bold" :class="kembalian < 0 ? 'text-red-600' : 'text-green-600'">{{ formatRp(kembalian) }}</span>
            </div>

            <div class="mt-3">
                <Label for="note">Catatan (opsional)</Label>
                <Input id="note" v-model="bayar.note" placeholder="Catatan transaksi..." class="mt-1.5" />
            </div>

            <Button type="button" class="mt-4 w-full bg-emerald-600 hover:bg-emerald-700" :disabled="!bayarValid || processing" @click="prosesBayar">
                {{ processing ? 'Memproses...' : `Bayar ${formatRp(total)}` }}
            </Button>
        </div>
    </div>

    <!-- Modal scan barcode -->
    <div v-if="showScan" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4" @click.self="tutupScan()">
        <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl border border-neutral-100">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-neutral-100 px-5 py-4 bg-emerald-50/50">
                <div class="flex items-center gap-2">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600 text-white shadow-xs">
                        <ScanBarcode class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-neutral-900">Scan Barcode Produk</h3>
                        <p class="text-xs text-neutral-500">Arahkan kamera ke barcode produk</p>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <button
                        v-if="canTorch"
                        type="button"
                        class="rounded-lg p-1.5 transition"
                        :class="torchOn ? 'bg-amber-100 text-amber-600' : 'text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700'"
                        @click="toggleTorch()"
                        :title="torchOn ? 'Matikan Senter' : 'Nyalakan Senter'"
                    >
                        <Flashlight v-if="!torchOn" class="h-4 w-4" />
                        <FlashlightOff v-else class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 transition"
                        @click="tutupScan()"
                        title="Tutup (Esc)"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <!-- Body -->
            <div class="p-5 space-y-3">
                <!-- Pilihan Kamera (jika lebih dari 1 kamera terdeteksi) -->
                <div v-if="daftarKamera.length > 1" class="flex items-center gap-2">
                    <SwitchCamera class="h-4 w-4 text-neutral-500 shrink-0" />
                    <select
                        v-model="kameraDipilih"
                        @change="gantiKamera(kameraDipilih)"
                        class="h-8 flex-1 rounded-md border border-neutral-200 bg-white px-2 text-xs text-neutral-700 focus:border-emerald-500 focus:outline-hidden focus:ring-1 focus:ring-emerald-500"
                    >
                        <option v-for="(kam, idx) in daftarKamera" :key="kam.deviceId" :value="kam.deviceId">
                            {{ kam.label || `Kamera ${idx + 1}` }}
                        </option>
                    </select>
                </div>

                <!-- Video Viewport / Scanner Frame -->
                <div
                    class="relative h-60 w-full overflow-hidden rounded-xl bg-black shadow-inner flex items-center justify-center select-none"
                    @touchstart="onTouchStartVideo"
                    @touchmove="onTouchMoveVideo"
                    @touchend="onTouchEndVideo"
                >
                    <video
                        ref="videoRef"
                        class="h-full w-full object-cover transition-transform duration-100 ease-out"
                        :style="{
                            transform: hasHardwareZoom ? 'none' : `scale(${zoomLevel})`,
                            transformOrigin: 'center center'
                        }"
                        muted
                        playsinline
                    />

                    <!-- Zoom Level Badge Overlay di Sudut Kanan Atas -->
                    <div
                        v-if="zoomLevel > 1"
                        class="pointer-events-none absolute top-2 right-2 rounded-md bg-black/60 backdrop-blur-xs px-2 py-0.5 text-[11px] font-bold text-emerald-300 border border-emerald-500/30"
                    >
                        {{ zoomLevel.toFixed(1) }}x
                    </div>

                    <!-- Loading Spinner Overlay -->
                    <div
                        v-if="scanLoading"
                        class="absolute inset-0 flex flex-col items-center justify-center bg-black/75 text-white gap-2"
                    >
                        <Loader2 class="h-7 w-7 animate-spin text-emerald-400" />
                        <span class="text-xs font-medium text-neutral-200">Memproses pemindaian...</span>
                    </div>

                    <!-- Scanning Frame Guide (Reticle + Laser) -->
                    <div
                        v-if="scanAktif && !scanLoading && !scanError"
                        class="pointer-events-none absolute inset-0 flex items-center justify-center transition-all duration-300"
                    >
                        <div
                            class="relative h-36 w-60 rounded-xl border-2 transition-all duration-200 flex items-center justify-center"
                            :class="scanSuccessPulse ? 'border-emerald-400 bg-emerald-500/25 shadow-[0_0_20px_#10b981]' : 'border-emerald-400/60'"
                        >
                            <!-- Tanda Centang Sukses saat Barcode Terdeteksi -->
                            <div v-if="scanSuccessPulse" class="flex flex-col items-center gap-1 text-emerald-300 animate-in fade-in zoom-in duration-200">
                                <Check class="h-10 w-10 text-emerald-300 drop-shadow-md" />
                                <span class="text-[11px] font-bold tracking-wide">TERDETEKSI!</span>
                            </div>

                            <!-- Sudut-sudut bidik kamera -->
                            <template v-else>
                                <div class="absolute -top-1 -left-1 h-4 w-4 border-t-2 border-l-2 border-emerald-400"></div>
                                <div class="absolute -top-1 -right-1 h-4 w-4 border-t-2 border-r-2 border-emerald-400"></div>
                                <div class="absolute -bottom-1 -left-1 h-4 w-4 border-b-2 border-l-2 border-emerald-400"></div>
                                <div class="absolute -bottom-1 -right-1 h-4 w-4 border-b-2 border-r-2 border-emerald-400"></div>

                                <!-- Laser line animasi -->
                                <div class="animate-scan-laser absolute left-2 right-2 h-0.5 bg-gradient-to-r from-transparent via-emerald-400 to-transparent shadow-[0_0_8px_#34d399]"></div>
                            </template>
                        </div>
                    </div>

                    <!-- Notifikasi Hasil Scan Terakhir -->
                    <div
                        v-if="pesanScanTerakhir"
                        class="pointer-events-none absolute bottom-3 left-3 right-3 rounded-lg px-3 py-1.5 text-xs font-semibold text-center text-white shadow-lg transition"
                        :class="pesanScanTerakhir.sukses ? 'bg-emerald-600/90' : 'bg-red-600/90'"
                    >
                        {{ pesanScanTerakhir.teks }}
                    </div>
                </div>

                <!-- Kontrol Zoom Scanner Barcode -->
                <div v-if="scanAktif && !scanLoading" class="flex items-center justify-between gap-2 rounded-xl bg-neutral-100/90 px-3 py-2 border border-neutral-200 shadow-2xs">
                    <div class="flex items-center gap-1.5 text-neutral-700 shrink-0">
                        <ZoomIn class="h-4 w-4 text-emerald-600" />
                        <span class="text-xs font-semibold">Zoom {{ zoomLevel.toFixed(1) }}x</span>
                    </div>

                    <!-- Slider Zoom -->
                    <div class="flex-1 mx-2 flex items-center gap-1.5">
                        <button
                            type="button"
                            class="p-1 text-neutral-500 hover:text-neutral-900 hover:bg-neutral-200 rounded transition disabled:opacity-30"
                            :disabled="zoomLevel <= minZoom"
                            @click="setZoom(zoomLevel - 0.2)"
                            title="Perkecil (-)"
                        >
                            <Minus class="h-3.5 w-3.5" />
                        </button>
                        <input
                            type="range"
                            :min="minZoom"
                            :max="maxZoom"
                            step="0.1"
                            v-model.number="zoomLevel"
                            @input="setZoom(zoomLevel)"
                            class="w-full h-1.5 bg-neutral-300 rounded-lg appearance-none cursor-pointer accent-emerald-600"
                        />
                        <button
                            type="button"
                            class="p-1 text-neutral-500 hover:text-neutral-900 hover:bg-neutral-200 rounded transition disabled:opacity-30"
                            :disabled="zoomLevel >= maxZoom"
                            @click="setZoom(zoomLevel + 0.2)"
                            title="Perbesar (+)"
                        >
                            <Plus class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <!-- Tombol Cepat Zoom (1x, 1.5x, 2x, 3x) -->
                    <div class="flex items-center gap-1 shrink-0">
                        <button
                            v-for="opt in [1, 1.5, 2, 3]"
                            :key="opt"
                            type="button"
                            class="px-2 py-0.5 text-[11px] font-bold rounded-md transition"
                            :class="Math.abs(zoomLevel - opt) < 0.1 ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white text-neutral-600 hover:bg-neutral-200 border border-neutral-200'"
                            @click="setZoom(opt)"
                        >
                            {{ opt }}x
                        </button>
                    </div>
                </div>

                <!-- Pesan Error Kamera (jika ada) -->
                <div v-if="scanError" class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800">
                    <p class="font-medium">{{ scanError }}</p>
                    <div class="mt-2 flex items-center gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            class="h-7 text-xs border-amber-300 text-amber-900 hover:bg-amber-100"
                            @click="mulaiKamera()"
                        >
                            <RefreshCw class="mr-1 h-3.5 w-3.5" /> Coba Nyalakan Kamera Lagi
                        </Button>
                    </div>
                </div>

                <!-- Opsi Alternatif: Upload Gambar / Paste Clipboard -->
                <input
                    type="file"
                    ref="fileInputRef"
                    accept="image/*"
                    class="hidden"
                    @change="onFileChange"
                />
                <div class="flex items-center justify-between gap-2 rounded-lg border border-neutral-200 bg-neutral-50/70 p-2 text-xs">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 font-medium text-emerald-700 hover:text-emerald-800 hover:underline"
                        @click="fileInputRef?.click()"
                    >
                        <Upload class="h-3.5 w-3.5 text-emerald-600" />
                        <span>Pilih Foto / Gambar Barcode</span>
                    </button>
                    <span class="text-[11px] text-neutral-400">atau tekan <b>Ctrl + V</b> untuk tempel</span>
                </div>

                <!-- Input Kode Manual di dalam Modal -->
                <div class="flex items-center gap-1.5">
                    <div class="relative flex-1">
                        <Keyboard class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-neutral-400" />
                        <Input
                            v-model="scanManualInput"
                            @keydown.enter.prevent="hasilScan(scanManualInput)"
                            placeholder="Input kode barcode manual..."
                            class="h-8 pl-8 pr-2 text-xs"
                        />
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        class="h-8 px-3 text-xs bg-emerald-600 hover:bg-emerald-700 text-white"
                        :disabled="!scanManualInput.trim()"
                        @click="hasilScan(scanManualInput)"
                    >
                        Scan
                    </Button>
                </div>

                <!-- Uji Coba Cepat dengan Barcode Produk Aktif -->
                <div v-if="produkHasil.some((p) => p.barcode)" class="rounded-lg bg-neutral-50 border border-neutral-100 p-2">
                    <div class="flex items-center gap-1 text-[11px] font-medium text-neutral-600 mb-1.5">
                        <Sparkles class="h-3 w-3 text-amber-500" />
                        <span>Klik contoh barcode produk untuk tes langsung:</span>
                    </div>
                    <div class="flex flex-wrap gap-1.5 max-h-20 overflow-y-auto">
                        <button
                            v-for="sb in produkHasil.filter((p) => p.barcode).slice(0, 8)"
                            :key="sb.id_barang"
                            type="button"
                            class="inline-flex items-center gap-1 rounded border border-emerald-200 bg-white px-2 py-0.5 text-[11px] text-emerald-800 hover:bg-emerald-50 hover:border-emerald-400 transition"
                            @click="hasilScan(sb.barcode!)"
                            :title="`Scan barcode ${sb.barcode} (${sb.nama})`"
                        >
                            <span class="font-mono font-bold">{{ formatBarcode(sb.barcode) }}</span>
                            <span class="text-neutral-400 max-w-[90px] truncate">· {{ sb.nama }}</span>
                        </button>
                    </div>
                </div>

                <!-- Footer Options -->
                <div class="flex items-center justify-between pt-1 text-xs text-neutral-500">
                    <label class="flex items-center gap-2 cursor-pointer select-none text-neutral-700 hover:text-emerald-700">
                        <input
                            type="checkbox"
                            v-model="scanBeruntun"
                            class="h-4 w-4 rounded border-neutral-300 text-emerald-600 focus:ring-emerald-500"
                        />
                        <span>Scan beruntun (kamera tetap terbuka)</span>
                    </label>
                    <span class="text-[11px] text-neutral-400">Esc: Tutup</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal struk -->
    <div v-if="showStruk && receipt" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 p-4" @click.self="showStruk = false">
        <div class="max-h-[90vh] w-full max-w-sm overflow-y-auto rounded-2xl bg-white p-5 shadow-xl">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-emerald-800">Struk Transaksi</h3>
                <button type="button" class="text-neutral-400 hover:text-emerald-700" @click="showStruk = false">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <div id="struk-print" class="mt-3 rounded-lg bg-white p-4 font-mono text-xs leading-relaxed text-neutral-800">
                <p class="text-center text-sm font-bold">{{ receipt.sekolah ?? 'POS SEKOLAH' }}</p>
                <p v-if="receipt.alamat_sekolah" class="text-center">{{ receipt.alamat_sekolah }}</p>
                <p class="mt-2 border-t border-dashed border-neutral-300 pt-2">No: #{{ receipt.id_penjualan }} · {{ receipt.tanggal }}</p>
                <p>Kasir: {{ receipt.kasir ?? '-' }}</p>
                <p v-if="receipt.pelanggan">Plg: {{ receipt.pelanggan }}</p>
                <div class="mt-2 border-t border-dashed border-neutral-300 pt-2">
                    <div v-for="(it, i) in receipt.items" :key="i" class="mb-1.5">
                        <p class="font-semibold">{{ it.nama }}</p>
                        <p class="flex justify-between">
                            <span>{{ it.qty }} x {{ formatRp(it.harga) }}</span>
                            <span>{{ formatRp(it.subtotal) }}</span>
                        </p>
                        <p v-if="it.diskon > 0" class="text-right">diskon: -{{ formatRp(it.diskon) }}</p>
                    </div>
                </div>
                <div class="mt-2 border-t border-dashed border-neutral-300 pt-2">
                    <p class="flex justify-between"><span>Subtotal</span><span>{{ formatRp(receipt.total_faktur + receipt.total_diskon) }}</span></p>
                    <p class="flex justify-between"><span>Diskon</span><span>-{{ formatRp(receipt.total_diskon) }}</span></p>
                    <p class="flex justify-between text-sm font-bold"><span>Total</span><span>{{ formatRp(receipt.total_faktur) }}</span></p>
                    <p class="flex justify-between"><span>Bayar ({{ receipt.cara_bayar }})</span><span>{{ formatRp(receipt.total_bayar) }}</span></p>
                    <p class="flex justify-between"><span>Kembalian</span><span>{{ formatRp(receipt.kembalian) }}</span></p>
                    <p class="capitalize">Status: {{ receipt.status_pembayaran }} ({{ receipt.jenis_transaksi }})</p>
                </div>
                <p class="mt-2 border-t border-dashed border-neutral-300 pt-2 text-center">Terima kasih atas kunjungan Anda</p>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2">
                <Button type="button" variant="outline" @click="showStruk = false">Tutup</Button>
                <Button type="button" class="bg-emerald-600 hover:bg-emerald-700" @click="cetakStruk()">
                    <Printer class="mr-1 h-4 w-4" /> Cetak
                </Button>
            </div>
        </div>
    </div>

    <!-- Modal Animasi Sukses Pembayaran & Confetti -->
    <div v-if="showSuccessAnimation" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 animate-in fade-in duration-200">
        <div class="w-full max-w-sm rounded-3xl bg-white dark:bg-neutral-900 p-6 shadow-2xl text-center space-y-4 border border-emerald-100 dark:border-neutral-800 animate-in zoom-in-95 duration-300">
            <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 shadow-inner animate-bounce">
                <Check class="h-10 w-10 stroke-[3]" />
            </div>
            <div>
                <h3 class="text-lg font-bold text-neutral-900 dark:text-white">Transaksi Berhasil!</h3>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Pembayaran berhasil diproses dan struk siap dicetak.</p>
            </div>
            <Button type="button" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-xl shadow-lg transition" @click="showSuccessAnimation = false">
                Transaksi Baru
            </Button>
        </div>
    </div>
</template>

<style scoped>
@keyframes scan-laser {
    0% {
        top: 8%;
        opacity: 0.6;
    }
    50% {
        top: 88%;
        opacity: 1;
    }
    100% {
        top: 8%;
        opacity: 0.6;
    }
}

.animate-scan-laser {
    animation: scan-laser 2.2s ease-in-out infinite;
}
</style>
