<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { HandCoins, ReceiptText, Search, X } from '@lucide/vue';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Piutang', href: '/piutang' }],
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

type Row = {
    id_penjualan: number; tanggal: string | null; pelanggan: string | null;
    kasir: string | null; sekolah: string | null;
    total_faktur: number; sudah_bayar: number; sisa: number; cara_bayar: string | null;
};
type Paginate<T> = { data: T[]; current_page: number; last_page: number; total: number };

const search = ref('');
const list = ref<Paginate<Row>>({ data: [], current_page: 1, last_page: 1, total: 0 });
const ringkasan = ref({ faktur: 0, total_piutang: 0 });
const curPage = ref(1);
const loading = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;

watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => { curPage.value = 1; muat(); }, 400);
});

async function muat() {
    loading.value = true;
    try {
        const q = new URLSearchParams({ search: search.value, page: String(curPage.value) });
        const r = await fetch(`/piutang/data?${q}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const j = await r.json();
        ringkasan.value = j.ringkasan;
        list.value = j.tabel;
    } catch { /* abaikan */ } finally { loading.value = false; }
}

// ---------- Modal lunasi ----------
const showBayar = ref(false);
const target = ref<Row | null>(null);
const f = reactive({ jumlah: 0, cara_bayar: 'cash' });

function bukaLunasi(r: Row) {
    target.value = r;
    f.jumlah = r.sisa;
    f.cara_bayar = 'cash';
    showBayar.value = true;
}

function simpan() {
    if (!target.value) return;
    router.post(`/piutang/${target.value.id_penjualan}/lunasi`, { ...f }, {
        preserveScroll: true,
        onSuccess: () => { showBayar.value = false; muat(); },
    });
}

onMounted(() => muat());

const formatRp = (n: number) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');
</script>

<template>
    <Head title="Piutang" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-emerald-800">Piutang</h1>
            <p class="mt-0.5 text-sm text-neutral-500">
                Transaksi kredit yang belum lunas
                <span v-if="sekolah?.nama_sekolah && !is_super_admin" class="font-medium text-neutral-700"> — {{ sekolah.nama_sekolah }}</span>
            </p>
        </div>

        <div v-if="successMsg" class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">{{ successMsg }}</div>
        <div v-if="formErrors.piutang" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ formErrors.piutang }}</div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-amber-100 bg-amber-50 p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="truncate text-xs font-medium text-neutral-500">Total Piutang</p>
                        <p class="mt-1 truncate text-xl font-bold text-neutral-900">{{ formatRp(ringkasan.total_piutang) }}</p>
                    </div>
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-500 text-white">
                        <HandCoins class="h-5 w-5" />
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-sky-100 bg-sky-50 p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="truncate text-xs font-medium text-neutral-500">Faktur Belum Lunas</p>
                        <p class="mt-1 truncate text-xl font-bold text-neutral-900">{{ ringkasan.faktur }}</p>
                    </div>
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sky-500 text-white">
                        <ReceiptText class="h-5 w-5" />
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
            <div class="relative w-full sm:max-w-xs">
                <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-emerald-600" />
                <Input v-model="search" placeholder="Cari ID / pelanggan..." class="pl-9" />
            </div>

            <div v-if="loading" class="py-8 text-center text-sm text-neutral-400">Memuat...</div>
            <div v-else-if="list.data.length === 0" class="mt-3 rounded-lg bg-neutral-50 px-4 py-8 text-center text-sm text-neutral-400">
                Tidak ada piutang. Semua transaksi lunas.
            </div>
            <div v-else class="mt-3">
                <!-- Kartu mobile -->
                <div class="space-y-2 sm:hidden">
                    <div v-for="(r, i) in list.data" :key="r.id_penjualan" class="rounded-lg border border-neutral-100 px-3 py-2.5 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <p class="truncate font-semibold text-neutral-900">#{{ r.id_penjualan }} · {{ r.pelanggan ?? '-' }}</p>
                            <span class="shrink-0 font-bold whitespace-nowrap text-amber-600">{{ formatRp(r.sisa) }}</span>
                        </div>
                        <p class="mt-1 text-xs whitespace-nowrap text-neutral-400">{{ r.tanggal ?? '-' }} · Total {{ formatRp(r.total_faktur) }} · Terbayar {{ formatRp(r.sudah_bayar) }}</p>
                        <button type="button" class="mt-2 flex h-11 w-full items-center justify-center rounded-md bg-emerald-600 text-sm font-medium text-white hover:bg-emerald-700" @click="bukaLunasi(r)">Lunasi</button>
                    </div>
                </div>
                <div class="hidden overflow-x-auto sm:block">
                <table class="w-full min-w-200 text-left text-sm">
                    <thead>
                        <tr class="border-b border-neutral-100 text-xs text-neutral-400">
                            <th class="py-2 pr-2 font-medium">No</th>
                            <th class="py-2 pr-2 font-medium">Faktur</th>
                            <th class="py-2 pr-2 font-medium">Tanggal</th>
                            <th class="py-2 pr-2 font-medium">Pelanggan</th>
                            <th v-if="is_super_admin" class="py-2 pr-2 font-medium">Sekolah</th>
                            <th class="py-2 pr-2 text-right font-medium">Total</th>
                            <th class="py-2 pr-2 text-right font-medium">Terbayar</th>
                            <th class="py-2 pr-2 text-right font-medium">Sisa</th>
                            <th class="py-2 text-center font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, i) in list.data" :key="r.id_penjualan" class="border-b border-neutral-50 last:border-0 hover:bg-neutral-50">
                            <td class="py-2 pr-2 text-neutral-500">{{ (list.current_page - 1) * 10 + i + 1 }}</td>
                            <td class="py-2 pr-2 font-medium text-neutral-900">#{{ r.id_penjualan }}</td>
                            <td class="py-2 pr-2 whitespace-nowrap text-neutral-600">{{ r.tanggal ?? '-' }}</td>
                            <td class="max-w-36 truncate py-2 pr-2 text-neutral-600">{{ r.pelanggan ?? '-' }}</td>
                            <td v-if="is_super_admin" class="max-w-44 truncate py-2 pr-2 text-neutral-600">{{ r.sekolah ?? '-' }}</td>
                            <td class="py-2 pr-2 text-right whitespace-nowrap text-neutral-700">{{ formatRp(r.total_faktur) }}</td>
                            <td class="py-2 pr-2 text-right whitespace-nowrap text-neutral-500">{{ formatRp(r.sudah_bayar) }}</td>
                            <td class="py-2 pr-2 text-right font-bold whitespace-nowrap text-amber-600">{{ formatRp(r.sisa) }}</td>
                            <td class="py-2 text-center">
                                <button type="button" class="rounded-md bg-emerald-600 px-3 py-1 text-xs font-medium text-white hover:bg-emerald-700" @click="bukaLunasi(r)">Lunasi</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <div class="mt-3 flex items-center justify-between text-sm text-neutral-500">
                    <span>Total {{ list.total }} faktur</span>
                    <div class="flex items-center gap-2">
                        <button type="button" class="flex h-10 min-w-10 items-center justify-center rounded-md border border-neutral-200 px-3 disabled:opacity-40" :disabled="curPage <= 1" @click="curPage--; muat()">‹</button>
                        <span>{{ curPage }} / {{ list.last_page }}</span>
                        <button type="button" class="flex h-10 min-w-10 items-center justify-center rounded-md border border-neutral-200 px-3 disabled:opacity-40" :disabled="curPage >= list.last_page" @click="curPage++; muat()">›</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal pelunasan -->
    <div v-if="showBayar && target" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 p-4" @click.self="showBayar = false">
        <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-emerald-800">Pelunasan #{{ target.id_penjualan }}</h3>
                <button type="button" class="text-neutral-400 hover:text-emerald-700" @click="showBayar = false"><X class="h-5 w-5" /></button>
            </div>
            <div class="mt-3 rounded-lg bg-neutral-50 p-3 text-sm">
                <div class="flex justify-between text-neutral-500"><span>Total faktur</span><span>{{ formatRp(target.total_faktur) }}</span></div>
                <div class="flex justify-between text-neutral-500"><span>Sudah terbayar</span><span>{{ formatRp(target.sudah_bayar) }}</span></div>
                <div class="flex justify-between font-bold text-neutral-900"><span>Sisa</span><span>{{ formatRp(target.sisa) }}</span></div>
            </div>
            <div class="mt-3 space-y-3">
                <div>
                    <Label>Jumlah Bayar</Label>
                    <Input v-model.number="f.jumlah" type="number" min="1" class="mt-1.5" />
                    <InputError :message="formErrors.jumlah" />
                </div>
                <div>
                    <Label>Cara Bayar</Label>
                    <select v-model="f.cara_bayar" class="mt-1.5 h-9 w-full rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20">
                        <option value="cash">Cash</option>
                        <option value="transfer">Transfer</option>
                        <option value="qris">QRIS</option>
                        <option value="debit">Debit</option>
                    </select>
                    <InputError :message="formErrors.cara_bayar" />
                </div>
            </div>
            <Button type="button" class="mt-4 w-full bg-emerald-600 hover:bg-emerald-700" @click="simpan()">Simpan Pembayaran</Button>
        </div>
    </div>
</template>
