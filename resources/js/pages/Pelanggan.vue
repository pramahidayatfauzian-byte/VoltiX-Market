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
        breadcrumbs: [{ title: 'Pelanggan', href: '/pelanggan' }],
    },
});

type PelangganOpt = {
    id_pelanggan: number;
    nama_pelanggan: string | null;
    telepon: string | null;
    alamat: string | null;
    id_kelompok_pelanggan?: number | null;
    kelompok?: { nama_kelompok: string | null } | null;
};

type Kelompok = { id: number; nama_kelompok: string | null };

const props = defineProps<{
    sekolah?: { id_sekolah: number; nama_sekolah: string | null } | null;
    kelompok_pelanggan: Kelompok[];
}>();

const page = usePage();
const flash = computed(() => page.props.flash as Record<string, unknown>);
const formErrors = computed(() => (page.props.errors ?? {}) as Record<string, string>);
const successMsg = computed(() => flash.value.success as string | undefined);

type Paginate<T> = { data: T[]; current_page: number; last_page: number; total: number };

const plSearch = ref('');
const plList = ref<Paginate<PelangganOpt>>({ data: [], current_page: 1, last_page: 1, total: 0 });
const plLoading = ref(false);
const plPage = ref(1);
let plTimer: ReturnType<typeof setTimeout> | undefined;

watch(plSearch, () => {
    clearTimeout(plTimer);
    plTimer = setTimeout(() => { plPage.value = 1; muatPelanggan(); }, 400);
});

async function muatPelanggan() {
    plLoading.value = true;
    try {
        const r = await fetch(
            `/pelanggan/data?search=${encodeURIComponent(plSearch.value)}&page=${plPage.value}`,
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } },
        );
        plList.value = await r.json();
    } catch { /* abaikan */ } finally { plLoading.value = false; }
}

const showPlForm = ref(false);
const plEdit = ref<PelangganOpt | null>(null);
const plForm = reactive({
    id_kelompok_pelanggan: '' as string | number,
    nama_pelanggan: '',
    telepon: '',
    alamat: '',
});

function bukaTambahPelanggan() {
    plEdit.value = null;
    plForm.id_kelompok_pelanggan = '';
    plForm.nama_pelanggan = '';
    plForm.telepon = '';
    plForm.alamat = '';
    showPlForm.value = true;
}

function bukaEditPelanggan(p: PelangganOpt) {
    plEdit.value = p;
    plForm.id_kelompok_pelanggan = p.id_kelompok_pelanggan ?? '';
    plForm.nama_pelanggan = p.nama_pelanggan ?? '';
    plForm.telepon = p.telepon ?? '';
    plForm.alamat = p.alamat ?? '';
    showPlForm.value = true;
}

function simpanPelanggan() {
    const payload = {
        id_kelompok_pelanggan: plForm.id_kelompok_pelanggan === '' ? null : Number(plForm.id_kelompok_pelanggan),
        nama_pelanggan: plForm.nama_pelanggan,
        telepon: plForm.telepon || null,
        alamat: plForm.alamat || null,
    };
    if (plEdit.value) {
        router.put(`/pelanggan/${plEdit.value.id_pelanggan}`, payload, {
            preserveScroll: true,
            onSuccess: () => { showPlForm.value = false; muatPelanggan(); },
        });
    } else {
        router.post('/pelanggan', payload, {
            preserveScroll: true,
            onSuccess: () => { showPlForm.value = false; muatPelanggan(); },
        });
    }
}

async function hapusPelanggan(id: number) {
    if (!(await konfirmasi('Hapus pelanggan ini?'))) return;
    router.delete(`/pelanggan/${id}`, { preserveScroll: true, onSuccess: () => muatPelanggan() });
}

onMounted(() => { muatPelanggan(); });
</script>

<template>
    <Head title="Pelanggan" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-emerald-800">Pelanggan</h1>
            <p class="mt-0.5 text-sm text-neutral-500">
                Kelola data pelanggan
                <span v-if="sekolah?.nama_sekolah" class="font-medium text-neutral-700"> — {{ sekolah.nama_sekolah }}</span>
            </p>
        </div>

        <div v-if="successMsg" class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">{{ successMsg }}</div>

        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="relative w-full sm:max-w-xs">
                    <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-emerald-600" />
                    <Input v-model="plSearch" placeholder="Cari nama / telepon / alamat..." class="pl-9" />
                </div>
                <Button type="button" class="h-11 w-full bg-emerald-600 hover:bg-emerald-700 sm:w-auto" @click="bukaTambahPelanggan">+ Tambah Pelanggan</Button>
            </div>

            <div v-if="plLoading" class="py-8 text-center text-sm text-neutral-400">Memuat...</div>
            <div v-else-if="plList.data.length === 0" class="mt-3 rounded-lg bg-neutral-50 px-4 py-8 text-center text-sm text-neutral-400">Belum ada pelanggan.</div>
            <div v-else class="mt-3">
                <div class="space-y-2 sm:hidden">
                    <div v-for="(p, i) in plList.data" :key="p.id_pelanggan" class="rounded-lg border border-neutral-100 px-3 py-2.5 text-sm">
                        <p class="truncate font-semibold text-neutral-900">{{ p.nama_pelanggan }}</p>
                        <p class="mt-0.5 truncate text-xs text-neutral-400">{{ p.telepon ?? '-' }} · {{ p.kelompok?.nama_kelompok ?? '-' }} · {{ p.alamat ?? '-' }}</p>
                        <div class="mt-2 flex gap-1 border-t border-neutral-50 pt-2">
                            <button type="button" class="flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-xs font-medium text-neutral-700 hover:bg-neutral-100" @click="bukaEditPelanggan(p)">Edit</button>
                            <button type="button" class="flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-xs font-medium text-red-600 hover:bg-red-50" @click="hapusPelanggan(p.id_pelanggan)">Hapus</button>
                        </div>
                    </div>
                </div>
                <div class="hidden overflow-x-auto sm:block">
                <table class="w-full min-w-160 text-left text-sm">
                    <thead>
                        <tr class="border-b border-neutral-100 text-xs text-neutral-400">
                            <th class="py-2 pr-2 font-medium">No</th>
                            <th class="py-2 pr-2 font-medium">Nama</th>
                            <th class="py-2 pr-2 font-medium">Telepon</th>
                            <th class="py-2 pr-2 font-medium">Alamat</th>
                            <th class="py-2 pr-2 font-medium">Kelompok</th>
                            <th class="py-2 text-center font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(p, i) in plList.data" :key="p.id_pelanggan" class="border-b border-neutral-50 last:border-0 hover:bg-neutral-50">
                            <td class="py-2 pr-2 text-neutral-500">{{ (plList.current_page - 1) * 10 + i + 1 }}</td>
                            <td class="py-2 pr-2 font-medium text-neutral-900">{{ p.nama_pelanggan }}</td>
                            <td class="py-2 pr-2 text-neutral-600">{{ p.telepon ?? '-' }}</td>
                            <td class="max-w-60 truncate py-2 pr-2 text-neutral-600">{{ p.alamat ?? '-' }}</td>
                            <td class="py-2 pr-2 text-neutral-600">{{ p.kelompok?.nama_kelompok ?? '-' }}</td>
                            <td class="py-2 text-center whitespace-nowrap">
                                <button type="button" class="rounded-md px-2 py-1 text-xs font-medium text-neutral-700 hover:bg-neutral-100" @click="bukaEditPelanggan(p)">Edit</button>
                                <button type="button" class="rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50" @click="hapusPelanggan(p.id_pelanggan)">Hapus</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <div class="mt-3 flex items-center justify-between text-sm text-neutral-500">
                    <span>Total {{ plList.total }} pelanggan</span>
                    <div class="flex items-center gap-2">
                        <button type="button" class="flex h-10 min-w-10 items-center justify-center rounded-md border border-neutral-200 px-3 disabled:opacity-40" :disabled="plPage <= 1" @click="plPage--; muatPelanggan()">‹</button>
                        <span>{{ plPage }} / {{ plList.last_page }}</span>
                        <button type="button" class="flex h-10 min-w-10 items-center justify-center rounded-md border border-neutral-200 px-3 disabled:opacity-40" :disabled="plPage >= plList.last_page" @click="plPage++; muatPelanggan()">›</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal form pelanggan -->
    <div v-if="showPlForm" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 p-4" @click.self="showPlForm = false">
        <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-emerald-800">{{ plEdit ? 'Edit Pelanggan' : 'Tambah Pelanggan' }}</h3>
                <button type="button" class="text-neutral-400 hover:text-emerald-700" @click="showPlForm = false"><X class="h-5 w-5" /></button>
            </div>
            <div class="mt-4 space-y-3">
                <div>
                    <Label>Kelompok</Label>
                    <select v-model="plForm.id_kelompok_pelanggan" class="mt-1.5 w-full h-9 rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20">
                        <option value="">-- Tanpa kelompok --</option>
                        <option v-for="k in kelompok_pelanggan" :key="k.id" :value="k.id">{{ k.nama_kelompok }}</option>
                    </select>
                    <InputError :message="formErrors.id_kelompok_pelanggan" />
                </div>
                <div>
                    <Label>Nama Pelanggan</Label>
                    <Input v-model="plForm.nama_pelanggan" class="mt-1.5" placeholder="Nama lengkap" />
                    <InputError :message="formErrors.nama_pelanggan" />
                </div>
                <div>
                    <Label>Telepon</Label>
                    <Input v-model="plForm.telepon" class="mt-1.5" placeholder="No. telepon" />
                    <InputError :message="formErrors.telepon" />
                </div>
                <div>
                    <Label>Alamat</Label>
                    <Input v-model="plForm.alamat" class="mt-1.5" placeholder="Alamat" />
                    <InputError :message="formErrors.alamat" />
                </div>
            </div>
            <Button type="button" class="mt-4 w-full bg-emerald-600 hover:bg-emerald-700" @click="simpanPelanggan">Simpan</Button>
        </div>
    </div>
</template>
