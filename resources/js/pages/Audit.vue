<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { History, Search } from '@lucide/vue';
import { onMounted, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Audit Log', href: '/audit' }],
    },
});

const props = defineProps<{
    sekolah?: { id_sekolah: number; nama_sekolah: string | null } | null;
    is_super_admin: boolean;
    modul_list: string[];
}>();

type Row = { waktu: string | null; aktor: string; aksi: string; modul: string; detail: string };

const fModul = ref('');
const fAksi = ref('');
const fSearch = ref('');
const rows = ref<Row[]>([]);
const curPage = ref(1);
const lastPage = ref(1);
const total = ref(0);
const loading = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;

watch(fSearch, () => {
    clearTimeout(timer);
    timer = setTimeout(() => { curPage.value = 1; muat(); }, 400);
});

async function muat() {
    loading.value = true;
    try {
        const q = new URLSearchParams({
            modul: fModul.value, aksi: fAksi.value,
            search: fSearch.value, page: String(curPage.value),
        });
        const r = await fetch(`/audit/data?${q}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const j = await r.json();
        rows.value = j.data;
        curPage.value = j.current_page;
        lastPage.value = j.last_page;
        total.value = j.total;
    } catch { /* abaikan */ } finally { loading.value = false; }
}

function terapkan() {
    curPage.value = 1;
    muat();
}

onMounted(() => muat());

const formatTgl = (s: string | null) => {
    if (!s) return '-';
    const d = new Date(s.replace(' ', 'T'));
    return isNaN(d.getTime()) ? s : d.toLocaleString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const badgeAksi = (a: string) =>
    a === 'tambah' ? 'bg-green-100 text-green-700'
    : a === 'edit' ? 'bg-sky-100 text-sky-700'
    : 'bg-red-100 text-red-600';
</script>

<template>
    <Head title="Audit Log" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="flex items-center gap-2 text-xl font-bold tracking-tight text-emerald-800">
                <History class="h-5 w-5" /> Audit Log
            </h1>
            <p class="mt-0.5 text-sm text-neutral-500">
                Jejak tambah, ubah, dan hapus data dari kolom yang tercatat di database
                <span v-if="sekolah?.nama_sekolah && !is_super_admin" class="font-medium text-neutral-700"> — {{ sekolah.nama_sekolah }}</span>
            </p>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-2 md:flex-row">
                <div class="relative w-full md:max-w-xs">
                    <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-emerald-600" />
                    <Input v-model="fSearch" placeholder="Cari aktivitas / user..." class="pl-9" />
                </div>
                <div class="grid grid-cols-2 gap-2 md:flex">
                <select v-model="fModul" class="h-9 w-full min-w-0 truncate rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20 md:w-auto" @change="terapkan()">
                    <option value="">Semua Modul</option>
                    <option v-for="m in modul_list" :key="m" :value="m">{{ m }}</option>
                </select>
                <select v-model="fAksi" class="h-9 w-full min-w-0 truncate rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20 md:w-auto" @change="terapkan()">
                    <option value="">Semua Aksi</option>
                    <option value="tambah">Tambah</option>
                    <option value="edit">Edit</option>
                    <option value="hapus">Hapus</option>
                </select>
                </div>
            </div>

            <div v-if="loading" class="py-8 text-center text-sm text-neutral-400">Memuat...</div>
            <div v-else-if="rows.length === 0" class="mt-3 rounded-lg bg-neutral-50 px-4 py-8 text-center text-sm text-neutral-400">
                Belum ada aktivitas tercatat.
            </div>
            <div v-else class="mt-3">
                <div class="space-y-2 sm:hidden">
                    <div v-for="(r, i) in rows" :key="i" class="rounded-lg border border-neutral-100 px-3 py-2.5 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <p class="truncate font-semibold text-neutral-700">{{ r.aktor }}</p>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold capitalize" :class="badgeAksi(r.aksi)">{{ r.aksi }}</span>
                        </div>
                        <p class="mt-1 truncate text-xs text-neutral-400">{{ r.modul }} · {{ formatTgl(r.waktu) }}</p>
                        <p class="mt-1 line-clamp-2 text-xs text-neutral-600">{{ r.detail }}</p>
                    </div>
                </div>
                <div class="hidden overflow-x-auto sm:block">
                <table class="w-full min-w-180 text-left text-sm">
                    <thead>
                        <tr class="border-b border-neutral-100 text-xs text-neutral-400">
                            <th class="py-2 pr-2 font-medium">Waktu</th>
                            <th class="py-2 pr-2 font-medium">User</th>
                            <th class="py-2 pr-2 text-center font-medium">Aksi</th>
                            <th class="py-2 pr-2 font-medium">Modul</th>
                            <th class="py-2 font-medium">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, i) in rows" :key="i" class="border-b border-neutral-50 last:border-0 hover:bg-neutral-50">
                            <td class="py-2 pr-2 whitespace-nowrap text-neutral-500">{{ formatTgl(r.waktu) }}</td>
                            <td class="max-w-40 truncate py-2 pr-2 font-medium text-neutral-700">{{ r.aktor }}</td>
                            <td class="py-2 pr-2 text-center">
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold capitalize" :class="badgeAksi(r.aksi)">{{ r.aksi }}</span>
                            </td>
                            <td class="py-2 pr-2 whitespace-nowrap text-neutral-600">{{ r.modul }}</td>
                            <td class="py-2 text-neutral-700">{{ r.detail }}</td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <div class="mt-3 flex items-center justify-between text-sm text-neutral-500">
                    <span>Total {{ total }} aktivitas</span>
                    <div class="flex items-center gap-2">
                        <button type="button" class="flex h-10 min-w-10 items-center justify-center rounded-md border border-neutral-200 px-3 disabled:opacity-40" :disabled="curPage <= 1" @click="curPage--; muat()">‹</button>
                        <span>{{ curPage }} / {{ lastPage }}</span>
                        <button type="button" class="flex h-10 min-w-10 items-center justify-center rounded-md border border-neutral-200 px-3 disabled:opacity-40" :disabled="curPage >= lastPage" @click="curPage++; muat()">›</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
