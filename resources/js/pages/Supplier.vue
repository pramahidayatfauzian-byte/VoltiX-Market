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
        breadcrumbs: [{ title: 'Supplier', href: '/supplier' }],
    },
});

type SekolahOpt = { id_sekolah: number; nama_sekolah: string | null };

const props = defineProps<{
    sekolah?: { id_sekolah: number; nama_sekolah: string | null } | null;
    sekolah_list: SekolahOpt[];
    is_super_admin: boolean;
}>();

const page = usePage();
const flash = computed(() => page.props.flash as Record<string, unknown>);
const formErrors = computed(() => (page.props.errors ?? {}) as Record<string, string>);
const successMsg = computed(() => flash.value.success as string | undefined);

type Paginate<T> = { data: T[]; current_page: number; last_page: number; total: number };
type SupRow = { id_supplier: number; nama: string | null; no_telepon: string | null; alamat_supplier: string | null; sekolah?: { nama_sekolah: string | null } | null };

const sSearch = ref('');
const sList = ref<Paginate<SupRow>>({ data: [], current_page: 1, last_page: 1, total: 0 });
const sPage = ref(1);
const sLoading = ref(false);
let sTimer: ReturnType<typeof setTimeout> | undefined;
watch(sSearch, () => { clearTimeout(sTimer); sTimer = setTimeout(() => { sPage.value = 1; muatSup(); }, 400); });

async function muatSup() {
    sLoading.value = true;
    try {
        const r = await fetch(`/supplier/data?search=${encodeURIComponent(sSearch.value)}&page=${sPage.value}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        sList.value = await r.json();
    } catch { /* abaikan */ } finally { sLoading.value = false; }
}

const showSupForm = ref(false);
const supEdit = ref<SupRow | null>(null);
const supForm = reactive({ id_sekolah: '' as string | number, nama: '', no_telepon: '', alamat_supplier: '' });

function bukaTambahSup() {
    supEdit.value = null;
    Object.assign(supForm, { id_sekolah: '', nama: '', no_telepon: '', alamat_supplier: '' });
    showSupForm.value = true;
}
function bukaEditSup(r: SupRow) {
    supEdit.value = r;
    Object.assign(supForm, { nama: r.nama ?? '', no_telepon: r.no_telepon ?? '', alamat_supplier: r.alamat_supplier ?? '' });
    showSupForm.value = true;
}
function simpanSup() {
    const payload = {
        nama: supForm.nama,
        no_telepon: supForm.no_telepon || null,
        alamat_supplier: supForm.alamat_supplier || null,
        ...(props.is_super_admin && supForm.id_sekolah !== '' ? { id_sekolah: Number(supForm.id_sekolah) } : {}),
    };
    if (supEdit.value) {
        router.put(`/supplier/${supEdit.value.id_supplier}`, payload, { preserveScroll: true, onSuccess: () => { showSupForm.value = false; muatSup(); } });
    } else {
        router.post('/supplier', payload, { preserveScroll: true, onSuccess: () => { showSupForm.value = false; muatSup(); } });
    }
}
async function hapusSup(id: number) {
    if (!(await konfirmasi('Hapus supplier ini?'))) return;
    router.delete(`/supplier/${id}`, { preserveScroll: true, onSuccess: () => muatSup() });
}

onMounted(() => { muatSup(); });
</script>

<template>
    <Head title="Supplier" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-emerald-800">Supplier</h1>
            <p class="mt-0.5 text-sm text-neutral-500">
                Kelola data supplier
                <span v-if="sekolah?.nama_sekolah" class="font-medium text-neutral-700"> — {{ sekolah.nama_sekolah }}</span>
            </p>
        </div>

        <div v-if="successMsg" class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">{{ successMsg }}</div>
        <div v-if="formErrors.supplier" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ formErrors.supplier }}</div>

        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="relative w-full sm:max-w-xs">
                    <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-emerald-600" />
                    <Input v-model="sSearch" placeholder="Cari supplier..." class="pl-9" />
                </div>
                <Button type="button" class="h-11 w-full bg-emerald-600 hover:bg-emerald-700 sm:w-auto" @click="bukaTambahSup">+ Tambah Supplier</Button>
            </div>
            <div v-if="sLoading" class="py-8 text-center text-sm text-neutral-400">Memuat...</div>
            <div v-else-if="sList.data.length === 0" class="mt-3 rounded-lg bg-neutral-50 px-4 py-8 text-center text-sm text-neutral-400">Belum ada supplier.</div>
            <div v-else class="mt-3">
                <div class="space-y-2 sm:hidden">
                    <div v-for="(r, i) in sList.data" :key="r.id_supplier" class="rounded-lg border border-neutral-100 px-3 py-2.5 text-sm">
                        <p class="truncate font-semibold text-neutral-900">{{ r.nama }}</p>
                        <p class="mt-0.5 truncate text-xs text-neutral-400">{{ r.no_telepon ?? '-' }} · {{ r.alamat_supplier ?? '-' }}</p>
                        <div class="mt-2 flex gap-1 border-t border-neutral-50 pt-2">
                            <button type="button" class="flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-xs font-medium text-neutral-700 hover:bg-neutral-100" @click="bukaEditSup(r)">Edit</button>
                            <button type="button" class="flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-xs font-medium text-red-600 hover:bg-red-50" @click="hapusSup(r.id_supplier)">Hapus</button>
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
                            <th class="py-2 text-center font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, i) in sList.data" :key="r.id_supplier" class="border-b border-neutral-50 last:border-0 hover:bg-neutral-50">
                            <td class="py-2 pr-2 text-neutral-500">{{ (sList.current_page - 1) * 10 + i + 1 }}</td>
                            <td class="py-2 pr-2 font-medium text-neutral-900">{{ r.nama }}</td>
                            <td class="py-2 pr-2 text-neutral-600">{{ r.no_telepon ?? '-' }}</td>
                            <td class="max-w-60 truncate py-2 pr-2 text-neutral-600">{{ r.alamat_supplier ?? '-' }}</td>
                            <td class="py-2 text-center whitespace-nowrap">
                                <button type="button" class="rounded-md px-2 py-1 text-xs font-medium text-neutral-700 hover:bg-neutral-100" @click="bukaEditSup(r)">Edit</button>
                                <button type="button" class="rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50" @click="hapusSup(r.id_supplier)">Hapus</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <div class="mt-3 flex items-center justify-between text-sm text-neutral-500">
                    <span>Total {{ sList.total }} supplier</span>
                    <div class="flex items-center gap-2">
                        <button type="button" class="flex h-10 min-w-10 items-center justify-center rounded-md border border-neutral-200 px-3 disabled:opacity-40" :disabled="sPage <= 1" @click="sPage--; muatSup()">‹</button>
                        <span>{{ sPage }} / {{ sList.last_page }}</span>
                        <button type="button" class="flex h-10 min-w-10 items-center justify-center rounded-md border border-neutral-200 px-3 disabled:opacity-40" :disabled="sPage >= sList.last_page" @click="sPage++; muatSup()">›</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal supplier -->
    <div v-if="showSupForm" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 p-4" @click.self="showSupForm = false">
        <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-emerald-800">{{ supEdit ? 'Edit Supplier' : 'Tambah Supplier' }}</h3>
                <button type="button" class="text-neutral-400 hover:text-emerald-700" @click="showSupForm = false"><X class="h-5 w-5" /></button>
            </div>
            <div class="mt-4 space-y-3">
                <div v-if="is_super_admin && !supEdit">
                    <Label>Sekolah (Tenant)</Label>
                    <select v-model="supForm.id_sekolah" class="mt-1.5 w-full h-9 rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20">
                        <option value="">-- Sekolah saya --</option>
                        <option v-for="s in sekolah_list" :key="s.id_sekolah" :value="s.id_sekolah">{{ s.nama_sekolah }}</option>
                    </select>
                </div>
                <div><Label>Nama Supplier</Label><Input v-model="supForm.nama" class="mt-1.5" /><InputError :message="formErrors.nama" /></div>
                <div><Label>No. Telepon</Label><Input v-model="supForm.no_telepon" class="mt-1.5" /><InputError :message="formErrors.no_telepon" /></div>
                <div><Label>Alamat</Label><Input v-model="supForm.alamat_supplier" class="mt-1.5" /><InputError :message="formErrors.alamat_supplier" /></div>
            </div>
            <Button type="button" class="mt-4 w-full bg-emerald-600 hover:bg-emerald-700" @click="simpanSup()">Simpan</Button>
        </div>
    </div>
</template>
