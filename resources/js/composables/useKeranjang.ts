import { computed, onMounted, ref } from 'vue';

const KUNCI_QTY = 'pos_keranjang_qty';
const KUNCI_ITEMS = 'pos_keranjang';

export type KeranjangItem = {
    id_barang: number;
    barcode: string | null;
    nama: string | null;
    harga_jual: number;
    stok: number;
    qty: number;
};

const qty = ref(0);

function bacaQty(): number {
    if (typeof window === 'undefined' || typeof localStorage === 'undefined') return 0;
    const v = Number(localStorage.getItem(KUNCI_QTY) || 0);
    return Number.isFinite(v) ? v : 0;
}

function tulisQty(n: number): void {
    qty.value = n;
    try {
        localStorage.setItem(KUNCI_QTY, String(n));
    } catch { /* abaikan */ }
}

function bacaItems(): KeranjangItem[] {
    if (typeof window === 'undefined' || typeof localStorage === 'undefined') return [];
    try {
        const raw = localStorage.getItem(KUNCI_ITEMS);
        return raw ? (JSON.parse(raw) as KeranjangItem[]) : [];
    } catch {
        return [];
    }
}

function tulisItems(items: KeranjangItem[]): void {
    try {
        localStorage.setItem(KUNCI_ITEMS, JSON.stringify(items));
    } catch { /* abaikan */ }
    tulisQty(items.reduce((s, i) => s + i.qty, 0));
    if (typeof window !== 'undefined') window.dispatchEvent(new Event('pos-keranjang'));
}

let terpasang = false;

function pasangListener(): void {
    if (terpasang || typeof window === 'undefined') return;
    terpasang = true;
    window.addEventListener('storage', (e) => {
        if (e.key === KUNCI_QTY) qty.value = bacaQty();
        if (e.key === KUNCI_ITEMS) qty.value = bacaQty();
    });
    window.addEventListener('pos-keranjang', (() => {
        qty.value = bacaQty();
    }) as EventListener);
}

export function useKeranjang() {
    onMounted(() => {
        qty.value = bacaQty();
        pasangListener();
    });

    if (typeof window !== 'undefined' && qty.value === 0) {
        const awal = bacaQty();
        if (awal) qty.value = awal;
        pasangListener();
    }

    return {
        qty: computed(() => qty.value),
        setQty: (n: number) => {
            tulisQty(n);
            window.dispatchEvent(new Event('pos-keranjang'));
        },
    };
}

export function setKeranjangQty(n: number): void {
    tulisQty(n);
    if (typeof window !== 'undefined') window.dispatchEvent(new Event('pos-keranjang'));
}

export function getKeranjangItems(): KeranjangItem[] {
    return bacaItems();
}

export function tambahKeKeranjang(p: {
    id_barang: number;
    barcode: string | null;
    nama: string | null;
    harga_jual: number;
    stok: number;
}): { ok: boolean; error?: string } {
    const items = bacaItems();
    const ada = items.find((i) => i.id_barang === p.id_barang);
    if (ada) {
        if (ada.qty + 1 > p.stok) return { ok: false, error: `Stok ${p.nama} tidak cukup (sisa ${p.stok}).` };
        ada.qty += 1;
    } else {
        if (p.stok < 1) return { ok: false, error: `Stok ${p.nama} habis.` };
        items.push({ ...p, qty: 1 });
    }
    tulisItems(items);
    return { ok: true };
}
