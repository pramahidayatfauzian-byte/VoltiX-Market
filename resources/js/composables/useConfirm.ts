import { reactive } from 'vue';

export type ConfirmVarian = 'danger' | 'utama';

export type ConfirmOpsi = {
    judul?: string;
    pesan: string;
    teksYa?: string;
    teksBatal?: string;
    varian?: ConfirmVarian;
};

// State global tunggal: cukup pasang <ConfirmDialog /> sekali di layout.
const state = reactive({
    terbuka: false,
    judul: 'Konfirmasi',
    pesan: '',
    teksYa: 'Ya, lanjutkan',
    teksBatal: 'Batal',
    varian: 'danger' as ConfirmVarian,
    putuskan: null as null | ((nilai: boolean) => void),
});

export function useConfirmState() {
    return state;
}

/**
 * Pengganti confirm() bawaan browser yang ramah mobile.
 * Contoh: if (!(await konfirmasi('Hapus produk ini?'))) return;
 */
export function konfirmasi(pesan: string | ConfirmOpsi): Promise<boolean> {
    const opsi: ConfirmOpsi = typeof pesan === 'string' ? { pesan } : pesan;
    // Batalkan dialog sebelumnya jika masih menggantung.
    state.putuskan?.(false);
    state.judul = opsi.judul ?? 'Konfirmasi';
    state.pesan = opsi.pesan;
    state.teksYa = opsi.teksYa ?? 'Ya, lanjutkan';
    state.teksBatal = opsi.teksBatal ?? 'Batal';
    state.varian = opsi.varian ?? 'danger';
    state.terbuka = true;
    return new Promise<boolean>((resolve) => {
        state.putuskan = resolve;
    });
}

export function jawabKonfirmasi(nilai: boolean) {
    state.terbuka = false;
    state.putuskan?.(nilai);
    state.putuskan = null;
}
