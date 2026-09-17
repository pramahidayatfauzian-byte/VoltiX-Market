<script setup lang="ts">
import {
    Check,
    Flashlight,
    FlashlightOff,
    Image as ImageIcon,
    Keyboard,
    Loader2,
    Minus,
    Plus,
    RefreshCw,
    ScanBarcode,
    SwitchCamera,
    Upload,
    X,
    ZoomIn,
} from '@lucide/vue';
import { nextTick, onUnmounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = withDefaults(
    defineProps<{
        open: boolean;
        judul?: string;
        subjudul?: string;
    }>(),
    { judul: 'Scan Barcode Produk', subjudul: 'Arahkan kamera ke barcode produk' },
);

const emit = defineEmits<{
    detected: [kode: string];
    close: [];
}>();

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

const videoRef = ref<HTMLVideoElement | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
const daftarKamera = ref<MediaDeviceInfo[]>([]);
const kameraDipilih = ref('');
const scanAktif = ref(false);
const memuat = ref(false);
const galat = ref('');
const torchOn = ref(false);
const bisaTorch = ref(false);
const pulseSukses = ref(false);
const pesan = ref<{ sukses: boolean; teks: string } | null>(null);
const manual = ref('');
const mengunggah = ref(false);
const zoomLevel = ref(1);
const minZoom = ref(1);
const maxZoom = ref(4);
const zoomHardware = ref(false);

let pinchAwal = 0;
let zoomAwalPinch = 1;

let mediaStream: MediaStream | null = null;
let loopTimer: ReturnType<typeof setInterval> | undefined;
let kanvas: HTMLCanvasElement | null = null;
let konteks: CanvasRenderingContext2D | null = null;
let sedangDecode = false;
let sudahDapat = false;
let timerPesan: ReturnType<typeof setTimeout> | undefined;
let escHandler: ((e: KeyboardEvent) => void) | null = null;

const FORMAT_NATIVE = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'code_93', 'itf', 'qr_code'];

function bunyiBeep() {
    try {
        const AudioCtx = window.AudioContext || (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        if (ctx.state === 'suspended') void ctx.resume();
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
        // abaikan
    }
}

function tampilkanPesan(sukses: boolean, teks: string) {
    pesan.value = { sukses, teks };
    if (timerPesan) clearTimeout(timerPesan);
    timerPesan = setTimeout(() => {
        pesan.value = null;
    }, 3000);
}

function hasilkan(kode: string) {
    const bersih = kode.trim();
    if (!bersih || sudahDapat) return;
    sudahDapat = true;
    bunyiBeep();
    pulseSukses.value = true;
    tampilkanPesan(true, `✓ ${bersih} terdeteksi`);
    // Tahan sebentar agar animasi sukses terlihat seperti di kasir.
    setTimeout(() => {
        hentikan();
        emit('detected', bersih);
    }, 450);
}

async function decodeKanvas(cvs: HTMLCanvasElement): Promise<string | null> {
    // 1. Native BarcodeDetector (cepat, tanpa unduhan)
    try {
        if ('BarcodeDetector' in window) {
            const didukung: string[] = await (window as unknown as { BarcodeDetector: { getSupportedFormats(): Promise<string[]> } }).BarcodeDetector.getSupportedFormats().catch(() => FORMAT_NATIVE);
            const valid = FORMAT_NATIVE.filter((f) => didukung.includes(f));
            if (valid.length > 0) {
                const detector = new (window as unknown as { BarcodeDetector: new (o: object) => { detect(s: object): Promise<Array<{ rawValue?: string }>> } }).BarcodeDetector({ formats: valid });
                const hasil = await detector.detect(cvs);
                if (hasil.length > 0 && hasil[0]?.rawValue) return hasil[0].rawValue;
            }
        }
    } catch {
        // lanjut ke ZXing
    }
    // 2. ZXing (diunduh malas saat pertama dipakai)
    try {
        const reader = await getCodeReader();
        const r = reader.decodeFromCanvas(cvs);
        if (r?.getText()) return r.getText();
    } catch {
        // belum terdeteksi di frame ini
    }
    return null;
}

async function mulaiKamera() {
    hentikan();
    memuat.value = true;
    galat.value = '';
    torchOn.value = false;
    bisaTorch.value = false;

    if (!videoRef.value) {
        memuat.value = false;
        return;
    }
    try {
        let stream: MediaStream;
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    deviceId: kameraDipilih.value ? { exact: kameraDipilih.value } : undefined,
                    facingMode: kameraDipilih.value ? undefined : { ideal: 'environment' },
                    width: { ideal: 1280, min: 640 },
                    height: { ideal: 720, min: 480 },
                },
                audio: false,
            });
        } catch {
            stream = await navigator.mediaDevices.getUserMedia({
                video: kameraDipilih.value ? { deviceId: { exact: kameraDipilih.value } } : { facingMode: 'environment' },
                audio: false,
            });
        }
        mediaStream = stream;
        const video = videoRef.value;
        video.srcObject = stream;
        await video.play().catch(() => {});

        // Kemampuan senter & zoom perangkat (zoom digital selalu tersedia)
        try {
            const track = stream.getVideoTracks()[0];
            const caps = (track?.getCapabilities?.() ?? {}) as { torch?: boolean; zoom?: { min?: number; max?: number } };
            bisaTorch.value = !!caps.torch;
            if (caps.zoom) {
                zoomHardware.value = true;
                minZoom.value = caps.zoom.min ?? 1;
                maxZoom.value = caps.zoom.max ?? 4;
            } else {
                zoomHardware.value = false;
                minZoom.value = 1;
                maxZoom.value = 4;
            }
            zoomLevel.value = 1;
        } catch {
            bisaTorch.value = false;
            zoomHardware.value = false;
        }

        // Daftar kamera (label terisi setelah izin diberikan)
        try {
            const { BrowserCodeReader } = await import('@zxing/browser');
            const devices = await BrowserCodeReader.listVideoInputDevices();
            if (devices.length > 0) {
                daftarKamera.value = devices;
                if (!kameraDipilih.value) kameraDipilih.value = devices[0].deviceId;
            }
        } catch {
            // abaikan
        }

        memuat.value = false;
        sudahDapat = false;
        scanAktif.value = true;
        loopTimer = setInterval(async () => {
            if (sedangDecode || sudahDapat || !videoRef.value) return;
            const v = videoRef.value;
            if (v.readyState !== v.HAVE_ENOUGH_DATA || v.videoWidth === 0) return;
            sedangDecode = true;
            try {
                // Zoom digital: potong area tengah (1/z) lalu perbesar ke kanvas decode,
                // sehingga hasil pindaian ikut terdampak zoom seperti tampilannya.
                const z = !zoomHardware.value && zoomLevel.value > 1 ? zoomLevel.value : 1;
                const srcW = v.videoWidth / z;
                const srcH = v.videoHeight / z;
                const srcX = (v.videoWidth - srcW) / 2;
                const srcY = (v.videoHeight - srcH) / 2;
                const w = Math.min(640, Math.round(srcW));
                const h = Math.round((w / srcW) * srcH);
                if (!kanvas) kanvas = document.createElement('canvas');
                if (kanvas.width !== w || kanvas.height !== h) {
                    kanvas.width = w;
                    kanvas.height = h;
                    konteks = kanvas.getContext('2d', { willReadFrequently: true });
                }
                konteks?.drawImage(v, srcX, srcY, srcW, srcH, 0, 0, w, h);
                if (kanvas) {
                    const kode = await decodeKanvas(kanvas);
                    if (kode) hasilkan(kode);
                }
            } finally {
                sedangDecode = false;
            }
        }, 250);
    } catch (e) {
        memuat.value = false;
        scanAktif.value = false;
        const nama = (e as { name?: string })?.name ?? '';
        galat.value = nama === 'NotAllowedError' || nama === 'PermissionDeniedError'
            ? 'Izin kamera ditolak. Berikan izin akses kamera pada pengaturan browser.'
            : 'Tidak dapat mengakses kamera. Coba unggah foto atau isi manual.';
    }
}

async function aturZoom(nilai: number) {
    const target = Math.min(maxZoom.value, Math.max(minZoom.value, Math.round(nilai * 10) / 10));
    zoomLevel.value = target;
    // Coba zoom optik perangkat dulu; bila gagal, zoom digital (CSS + crop decode) yang bekerja.
    const track = mediaStream?.getVideoTracks()[0];
    if (track && zoomHardware.value) {
        try {
            await (track as MediaStreamTrack & { applyConstraints(c: object): Promise<void> }).applyConstraints({ advanced: [{ zoom: target }] });
        } catch {
            zoomHardware.value = false;
        }
    }
}

// Cubit (pinch) dua jari pada video untuk zoom, seperti di halaman kasir.
function sentuhMulai(e: TouchEvent) {
    if (e.touches.length === 2) {
        pinchAwal = Math.hypot(
            e.touches[0].clientX - e.touches[1].clientX,
            e.touches[0].clientY - e.touches[1].clientY,
        );
        zoomAwalPinch = zoomLevel.value;
    }
}

function sentuhGerak(e: TouchEvent) {
    if (e.touches.length === 2 && pinchAwal > 0) {
        e.preventDefault();
        const jarak = Math.hypot(
            e.touches[0].clientX - e.touches[1].clientX,
            e.touches[0].clientY - e.touches[1].clientY,
        );
        void aturZoom(zoomAwalPinch * (jarak / pinchAwal));
    }
}

function sentuhSelesai() {
    pinchAwal = 0;
}

async function gantiTorch() {
    if (!mediaStream) return;
    const track = mediaStream.getVideoTracks()[0];
    if (!track) return;
    try {
        const next = !torchOn.value;
        await (track as MediaStreamTrack & { applyConstraints(c: object): Promise<void> }).applyConstraints({ advanced: [{ torch: next }] });
        torchOn.value = next;
    } catch {
        // abaikan
    }
}

async function gantiKamera(id: string) {
    kameraDipilih.value = id;
    await mulaiKamera();
}

async function unggahFoto(e: Event) {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file) return;
    mengunggah.value = true;
    galat.value = '';
    try {
        const url = URL.createObjectURL(file);
        const img = new Image();
        await new Promise<void>((resolve, reject) => {
            img.onload = () => resolve();
            img.onerror = () => reject(new Error('gagal'));
            img.src = url;
        });
        const skala = Math.min(1, 1200 / Math.max(img.naturalWidth, img.naturalHeight));
        const cvs = document.createElement('canvas');
        cvs.width = Math.max(1, Math.floor(img.naturalWidth * skala));
        cvs.height = Math.max(1, Math.floor(img.naturalHeight * skala));
        cvs.getContext('2d')?.drawImage(img, 0, 0, cvs.width, cvs.height);
        URL.revokeObjectURL(url);
        const kode = await decodeKanvas(cvs);
        if (kode) hasilkan(kode);
        else {
            galat.value = '';
            tampilkanPesan(false, '✗ Barcode tidak terbaca dari foto.');
        }
    } catch {
        tampilkanPesan(false, '✗ Gagal membaca foto.');
    } finally {
        mengunggah.value = false;
    }
}

function kirimManual() {
    if (manual.value.trim()) hasilkan(manual.value);
}

function hentikan() {
    scanAktif.value = false;
    memuat.value = false;
    if (loopTimer) {
        clearInterval(loopTimer);
        loopTimer = undefined;
    }
    if (mediaStream) {
        try {
            mediaStream.getTracks().forEach((t) => t.stop());
        } catch {
            // abaikan
        }
        mediaStream = null;
    }
    if (videoRef.value) videoRef.value.srcObject = null;
    torchOn.value = false;
    bisaTorch.value = false;
    zoomLevel.value = 1;
    zoomHardware.value = false;
    pulseSukses.value = false;
}

function tutup() {
    hentikan();
    emit('close');
}

watch(
    () => props.open,
    async (buka) => {
        if (buka) {
            manual.value = '';
            galat.value = '';
            pesan.value = null;
            pulseSukses.value = false;
            sudahDapat = false;
            await nextTick();
            if (!navigator?.mediaDevices?.getUserMedia) {
                galat.value = 'Kamera memerlukan koneksi aman (HTTPS atau localhost). Silakan unggah foto atau isi manual.';
                return;
            }
            // Unduh ZXing lebih awal selagi menunggu izin kamera.
            getCodeReader().catch(() => null);
            await mulaiKamera();
            escHandler = (e: KeyboardEvent) => {
                if (e.key === 'Escape') tutup();
            };
            window.addEventListener('keydown', escHandler);
        } else {
            hentikan();
            if (escHandler) {
                window.removeEventListener('keydown', escHandler);
                escHandler = null;
            }
        }
    },
);

onUnmounted(() => {
    hentikan();
    if (timerPesan) clearTimeout(timerPesan);
    if (escHandler) window.removeEventListener('keydown', escHandler);
});
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4" @click.self="tutup()">
        <div class="max-h-[92vh] w-full max-w-md overflow-hidden overflow-y-auto rounded-2xl border border-neutral-100 bg-white shadow-2xl">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-neutral-100 bg-emerald-50/50 px-5 py-4">
                <div class="flex items-center gap-2">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600 text-white shadow-xs">
                        <ScanBarcode class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-neutral-900">{{ judul }}</h3>
                        <p class="text-xs text-neutral-500">{{ subjudul }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <button
                        v-if="bisaTorch"
                        type="button"
                        class="rounded-lg p-1.5 transition"
                        :class="torchOn ? 'bg-amber-100 text-amber-600' : 'text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700'"
                        :title="torchOn ? 'Matikan Senter' : 'Nyalakan Senter'"
                        @click="gantiTorch()"
                    >
                        <Flashlight v-if="!torchOn" class="h-4 w-4" />
                        <FlashlightOff v-else class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-700"
                        title="Tutup (Esc)"
                        @click="tutup()"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <!-- Body -->
            <div class="space-y-3 p-5">
                <!-- Pilihan kamera -->
                <div v-if="daftarKamera.length > 1" class="flex items-center gap-2">
                    <SwitchCamera class="h-4 w-4 shrink-0 text-neutral-500" />
                    <select
                        :value="kameraDipilih"
                        class="h-8 flex-1 rounded-md border border-neutral-200 bg-white px-2 text-xs text-neutral-700 focus:border-emerald-500 focus:outline-hidden focus:ring-1 focus:ring-emerald-500"
                        @change="gantiKamera(($event.target as HTMLSelectElement).value)"
                    >
                        <option v-for="(kam, idx) in daftarKamera" :key="kam.deviceId" :value="kam.deviceId">
                            {{ kam.label || `Kamera ${idx + 1}` }}
                        </option>
                    </select>
                </div>

                <!-- Video viewport + reticle -->
                <div
                    class="relative flex h-60 w-full items-center justify-center overflow-hidden rounded-xl bg-black shadow-inner select-none"
                    @touchstart.passive="sentuhMulai"
                    @touchmove="sentuhGerak"
                    @touchend="sentuhSelesai"
                >
                    <video
                        ref="videoRef"
                        class="h-full w-full object-cover transition-transform duration-100 ease-out"
                        :style="!zoomHardware && zoomLevel > 1 ? { transform: `scale(${zoomLevel})`, transformOrigin: 'center center' } : undefined"
                        muted
                        playsinline
                    />

                    <!-- Badge zoom -->
                    <div
                        v-if="zoomLevel > 1"
                        class="pointer-events-none absolute top-2 right-2 rounded-md border border-emerald-500/30 bg-black/60 px-2 py-0.5 text-[11px] font-bold text-emerald-300 backdrop-blur-xs"
                    >
                        {{ zoomLevel.toFixed(1) }}x
                    </div>

                    <!-- Loading -->
                    <div v-if="memuat || mengunggah" class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-black/75 text-white">
                        <Loader2 class="h-7 w-7 animate-spin text-emerald-400" />
                        <span class="text-xs font-medium text-neutral-200">{{ mengunggah ? 'Membaca foto...' : 'Memproses pemindaian...' }}</span>
                    </div>

                    <!-- Reticle + laser -->
                    <div
                        v-if="scanAktif && !memuat && !galat"
                        class="pointer-events-none absolute inset-0 flex items-center justify-center transition-all duration-300"
                    >
                        <div
                            class="relative flex h-36 w-60 items-center justify-center rounded-xl border-2 transition-all duration-200"
                            :class="pulseSukses ? 'border-emerald-400 bg-emerald-500/25 shadow-[0_0_20px_#10b981]' : 'border-emerald-400/60'"
                        >
                            <div v-if="pulseSukses" class="flex flex-col items-center gap-1 text-emerald-300">
                                <Check class="h-10 w-10 text-emerald-300 drop-shadow-md" />
                                <span class="text-[11px] font-bold tracking-wide">TERDETEKSI!</span>
                            </div>
                            <template v-else>
                                <div class="absolute -top-1 -left-1 h-4 w-4 border-t-2 border-l-2 border-emerald-400"></div>
                                <div class="absolute -top-1 -right-1 h-4 w-4 border-t-2 border-r-2 border-emerald-400"></div>
                                <div class="absolute -bottom-1 -left-1 h-4 w-4 border-b-2 border-l-2 border-emerald-400"></div>
                                <div class="absolute -bottom-1 -right-1 h-4 w-4 border-b-2 border-r-2 border-emerald-400"></div>
                                <div class="pemindai-laser absolute top-[8%] right-2 left-2 h-[3px] rounded-full bg-gradient-to-r from-transparent via-emerald-400 to-transparent shadow-[0_0_10px_#34d399]"></div>
                            </template>
                        </div>
                    </div>

                    <!-- Notifikasi hasil -->
                    <div
                        v-if="pesan"
                        class="pointer-events-none absolute right-3 bottom-3 left-3 rounded-lg px-3 py-1.5 text-center text-xs font-semibold text-white shadow-lg transition"
                        :class="pesan.sukses ? 'bg-emerald-600/90' : 'bg-red-600/90'"
                    >
                        {{ pesan.teks }}
                    </div>
                </div>

                <!-- Kontrol zoom (selalu tersedia: optik bila didukung, digital bila tidak) -->
                <div v-if="scanAktif && !memuat" class="flex items-center justify-between gap-2 rounded-xl border border-neutral-200 bg-neutral-100/90 px-3 py-2 shadow-2xs">
                    <div class="flex shrink-0 items-center gap-1.5 text-neutral-700">
                        <ZoomIn class="h-4 w-4 text-emerald-600" />
                        <span class="text-xs font-semibold">Zoom {{ zoomLevel.toFixed(1) }}x</span>
                    </div>
                    <div class="mx-2 flex flex-1 items-center gap-1.5">
                        <button
                            type="button"
                            class="rounded p-1 text-neutral-500 transition hover:bg-neutral-200 hover:text-neutral-900 disabled:opacity-30"
                            :disabled="zoomLevel <= minZoom"
                            title="Perkecil (-)"
                            @click="aturZoom(zoomLevel - 0.5)"
                        >
                            <Minus class="h-3.5 w-3.5" />
                        </button>
                        <input
                            type="range"
                            :min="minZoom"
                            :max="maxZoom"
                            step="0.1"
                            :value="zoomLevel"
                            class="h-1.5 w-full cursor-pointer appearance-none rounded-lg bg-neutral-300 accent-emerald-600"
                            @input="aturZoom(Number(($event.target as HTMLInputElement).value))"
                        />
                        <button
                            type="button"
                            class="rounded p-1 text-neutral-500 transition hover:bg-neutral-200 hover:text-neutral-900 disabled:opacity-30"
                            :disabled="zoomLevel >= maxZoom"
                            title="Perbesar (+)"
                            @click="aturZoom(zoomLevel + 0.5)"
                        >
                            <Plus class="h-3.5 w-3.5" />
                        </button>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <button
                            v-for="opt in [1, 2, 3]"
                            :key="opt"
                            type="button"
                            class="rounded-md px-2 py-0.5 text-[11px] font-bold transition"
                            :class="Math.abs(zoomLevel - opt) < 0.1 ? 'bg-emerald-600 text-white shadow-xs' : 'border border-neutral-200 bg-white text-neutral-600 hover:bg-neutral-200'"
                            @click="aturZoom(opt)"
                        >
                            {{ opt }}x
                        </button>
                    </div>
                </div>

                <!-- Error kamera -->
                <div v-if="galat" class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                    <p class="font-medium">{{ galat }}</p>
                    <div class="mt-2 flex items-center gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            class="h-7 border-amber-300 text-xs text-amber-900 hover:bg-amber-100"
                            @click="mulaiKamera()"
                        >
                            <RefreshCw class="mr-1 h-3.5 w-3.5" /> Coba Nyalakan Kamera Lagi
                        </Button>
                    </div>
                </div>

                <!-- Upload foto -->
                <input ref="fileInputRef" type="file" accept="image/*" class="hidden" @change="unggahFoto" />
                <div class="flex items-center justify-between gap-2 rounded-lg border border-neutral-200 bg-neutral-50/70 p-2 text-xs">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 font-medium text-emerald-700 hover:text-emerald-800 hover:underline"
                        @click="fileInputRef?.click()"
                    >
                        <Upload class="h-3.5 w-3.5 text-emerald-600" />
                        <span>Pilih Foto / Gambar Barcode</span>
                    </button>
                    <span class="flex items-center gap-1 text-[11px] text-neutral-400">
                        <ImageIcon class="h-3.5 w-3.5" /> dari galeri
                    </span>
                </div>

                <!-- Input manual -->
                <div class="flex items-center gap-1.5">
                    <div class="relative flex-1">
                        <Keyboard class="pointer-events-none absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-neutral-400" />
                        <Input
                            v-model="manual"
                            placeholder="Input kode barcode manual..."
                            class="h-8 pr-2 pl-8 font-mono text-xs"
                            autocomplete="off"
                            @keydown.enter.prevent="kirimManual()"
                        />
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        class="h-8 bg-emerald-600 px-3 text-xs text-white hover:bg-emerald-700"
                        :disabled="!manual.trim()"
                        @click="kirimManual()"
                    >
                        Scan
                    </Button>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between pt-1 text-xs text-neutral-500">
                    <span class="text-[11px] text-neutral-400">Arahkan barcode ke dalam bingkai</span>
                    <span class="text-[11px] text-neutral-400">Esc: Tutup</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes pemindai-laser-gerak {
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

.pemindai-laser {
    animation: pemindai-laser-gerak 2.2s ease-in-out infinite;
    will-change: top;
}
</style>
