<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { KeyRound, Search, X } from '@lucide/vue';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { konfirmasi } from '@/composables/useConfirm';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'User', href: '/user' }],
    },
});

type Role = { id_role: number; nama_role: string | null };
type SekolahOpt = { id_sekolah: number; nama_sekolah: string | null };

const props = defineProps<{
    sekolah?: { id_sekolah: number; nama_sekolah: string | null } | null;
    roles: Role[];
    sekolah_list: SekolahOpt[];
    is_super_admin: boolean;
    role_saya?: string | null;
    bisa_kelola?: boolean;
}>();

const bisaKelola = computed(() => props.bisa_kelola ?? props.is_super_admin);
// Reset password: developer & super admin saja (admin lihat saja, tanpa edit/reset).
const bisaReset = computed(() =>
    props.role_saya ? ['developer', 'super admin'].includes(props.role_saya) : props.is_super_admin,
);

const page = usePage();
const flash = computed(() => page.props.flash as Record<string, unknown>);
const formErrors = computed(() => (page.props.errors ?? {}) as Record<string, string>);
const successMsg = computed(() => flash.value.success as string | undefined);

type UserRow = {
    id_user: number;
    username: string | null;
    nama_lengkap: string | null;
    is_active: boolean;
    id_role: number | null;
    id_sekolah: number | null;
    role?: { nama_role: string | null } | null;
    sekolah?: { nama_sekolah: string | null } | null;
};
type Paginate<T> = { data: T[]; current_page: number; last_page: number; total: number };

const search = ref('');
const roleFilter = ref('');
const statusFilter = ref('semua');
const list = ref<Paginate<UserRow>>({ data: [], current_page: 1, last_page: 1, total: 0 });
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
        const q = new URLSearchParams({
            search: search.value, page: String(curPage.value),
            id_role: roleFilter.value, status: statusFilter.value,
        });
        const r = await fetch(`/user/data?${q}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (r.status === 403) {
            list.value = { data: [], current_page: 1, last_page: 1, total: 0 };
            return;
        }
        list.value = await r.json();
    } catch { /* abaikan */ } finally { loading.value = false; }
}

function terapkanFilter() {
    curPage.value = 1;
    muat();
}

// ---------- Form tambah/edit ----------
const showForm = ref(false);
const editRow = ref<UserRow | null>(null);
const f = reactive({
    id_sekolah: '' as string | number,
    id_role: '' as string | number,
    nama_lengkap: '',
    username: '',
    password: '',
    is_active: true,
});

function bukaTambah() {
    editRow.value = null;
    Object.assign(f, { id_sekolah: '', id_role: '', nama_lengkap: '', username: '', password: '', is_active: true });
    showForm.value = true;
}
function bukaEdit(r: UserRow) {
    editRow.value = r;
    Object.assign(f, {
        id_sekolah: r.id_sekolah ?? '', id_role: r.id_role ?? '',
        nama_lengkap: r.nama_lengkap ?? '', username: r.username ?? '',
        password: '', is_active: !!r.is_active,
    });
    showForm.value = true;
}
function simpan() {
    const payload: Record<string, unknown> = {
        id_role: Number(f.id_role),
        nama_lengkap: f.nama_lengkap,
        username: f.username,
        is_active: f.is_active,
    };
    if (props.is_super_admin && f.id_sekolah !== '') payload.id_sekolah = Number(f.id_sekolah);
    if (f.password) payload.password = f.password;
    if (!editRow.value && !f.password) {
        // password wajib saat tambah — biarkan validasi backend yang menolak
    }
    if (editRow.value) {
        router.put(`/user/${editRow.value.id_user}`, payload, {
            preserveScroll: true,
            onSuccess: () => { showForm.value = false; muat(); },
        });
    } else {
        router.post('/user', { ...payload, password: f.password }, {
            preserveScroll: true,
            onSuccess: () => { showForm.value = false; muat(); },
        });
    }
}
async function hapus(id: number) {
    if (!(await konfirmasi('Hapus user ini?'))) return;
    router.delete(`/user/${id}`, { preserveScroll: true, onSuccess: () => muat() });
}
function toggle(id: number) {
    router.patch(`/user/${id}/toggle`, {}, { preserveScroll: true, onSuccess: () => muat() });
}

// ---------- Reset password ----------
const showReset = ref(false);
const resetRow = ref<UserRow | null>(null);
const resetForm = reactive({ password: '', password_confirmation: '' });
function bukaReset(r: UserRow) {
    resetRow.value = r;
    resetForm.password = '';
    resetForm.password_confirmation = '';
    showReset.value = true;
}
function prosesReset() {
    if (!resetRow.value) return;
    router.post(`/user/${resetRow.value.id_user}/reset-password`, { ...resetForm }, {
        preserveScroll: true,
        onSuccess: () => { showReset.value = false; },
    });
}

onMounted(() => muat());
</script>

<template>
    <Head title="Data User" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-emerald-800">Data User</h1>
            <p class="mt-0.5 text-sm text-neutral-500">
                {{ bisaKelola ? 'Kelola akun pengguna per tenant' : bisaReset ? 'Reset password pengguna sekolah Anda' : 'Lihat data pengguna sekolah Anda (read-only)' }}
                <span v-if="sekolah?.nama_sekolah" class="font-medium text-neutral-700"> — {{ sekolah.nama_sekolah }}</span>
            </p>
        </div>

        <div v-if="successMsg" class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">{{ successMsg }}</div>
        <div v-if="formErrors.user" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ formErrors.user }}</div>

        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-1 flex-col gap-2 sm:flex-row">
                    <div class="relative w-full sm:max-w-xs">
                        <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-emerald-600" />
                        <Input v-model="search" placeholder="Cari user..." class="pl-9" />
                    </div>
                    <select v-model="roleFilter" class="h-9 w-full rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20 sm:w-auto" @change="terapkanFilter()">
                        <option value="">Semua Role</option>
                        <option v-for="r in roles" :key="r.id_role" :value="r.id_role" class="capitalize">{{ r.nama_role }}</option>
                    </select>
                    <select v-model="statusFilter" class="h-9 w-full rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20 sm:w-auto" @change="terapkanFilter()">
                        <option value="semua">Semua Status</option>
                        <option value="aktif">Active</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>
                <Button v-if="bisaKelola" type="button" class="h-11 w-full bg-emerald-600 hover:bg-emerald-700 lg:w-auto" @click="bukaTambah">+ Tambah User</Button>
            </div>

            <div v-if="loading" class="py-8 text-center text-sm text-neutral-400">Memuat...</div>
            <div v-else-if="list.data.length === 0" class="mt-3 rounded-lg bg-neutral-50 px-4 py-8 text-center text-sm text-neutral-400">Belum ada user.</div>
            <div v-else class="mt-3">
                <div class="space-y-2 sm:hidden">
                    <div v-for="(r, i) in list.data" :key="r.id_user" class="rounded-lg border border-neutral-100 px-3 py-2.5 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <p class="truncate font-semibold text-neutral-900">{{ r.nama_lengkap }}</p>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold" :class="r.is_active ? 'bg-green-100 text-green-700' : 'bg-neutral-100 text-neutral-500'">{{ r.is_active ? 'Active' : 'Nonaktif' }}</span>
                        </div>
                        <p class="mt-0.5 truncate text-xs text-neutral-400">@{{ r.username }} · <span class="capitalize">{{ r.role?.nama_role ?? '-' }}</span></p>
                        <div class="mt-2 flex gap-1 overflow-x-auto border-t border-neutral-50 pt-2">
                            <button v-if="bisaKelola" type="button" class="flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-xs font-medium text-neutral-700 hover:bg-neutral-100" @click="bukaEdit(r)">Edit</button>
                            <button v-if="bisaKelola" type="button" class="flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-xs font-medium text-amber-600 hover:bg-amber-50" @click="toggle(r.id_user)">{{ r.is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                            <button v-if="bisaReset" type="button" class="flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-xs font-medium text-blue-600 hover:bg-blue-50" @click="bukaReset(r)">Reset PW</button>
                            <button v-if="bisaKelola" type="button" class="flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-xs font-medium text-red-600 hover:bg-red-50" @click="hapus(r.id_user)">Hapus</button>
                        </div>
                    </div>
                </div>
                <div class="hidden overflow-x-auto sm:block">
                <table class="w-full min-w-180 text-left text-sm">
                    <thead>
                        <tr class="border-b border-neutral-100 text-xs text-neutral-400">
                            <th class="py-2 pr-2 font-medium">No</th>
                            <th class="py-2 pr-2 font-medium">Nama</th>
                            <th class="py-2 pr-2 font-medium">Username</th>
                            <th class="py-2 pr-2 font-medium">Role</th>
                            <th v-if="is_super_admin" class="py-2 pr-2 font-medium">Sekolah</th>
                            <th class="py-2 pr-2 text-center font-medium">Status</th>
                            <th class="py-2 text-center font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, i) in list.data" :key="r.id_user" class="border-b border-neutral-50 last:border-0 hover:bg-neutral-50">
                            <td class="py-2 pr-2 text-neutral-500">{{ (list.current_page - 1) * 10 + i + 1 }}</td>
                            <td class="py-2 pr-2 font-medium text-neutral-900">{{ r.nama_lengkap }}</td>
                            <td class="py-2 pr-2 text-neutral-600">{{ r.username }}</td>
                            <td class="py-2 pr-2 capitalize text-neutral-600">{{ r.role?.nama_role ?? '-' }}</td>
                            <td v-if="is_super_admin" class="max-w-44 truncate py-2 pr-2 text-neutral-600">{{ r.sekolah?.nama_sekolah ?? '-' }}</td>
                            <td class="py-2 pr-2 text-center">
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="r.is_active ? 'bg-green-100 text-green-700' : 'bg-neutral-100 text-neutral-500'">
                                    {{ r.is_active ? 'Active' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="py-2 text-center whitespace-nowrap">
                                <button v-if="bisaKelola" type="button" class="rounded-md px-2 py-1 text-xs font-medium text-neutral-700 hover:bg-neutral-100" @click="bukaEdit(r)">Edit</button>
                                <button v-if="bisaKelola" type="button" class="rounded-md px-2 py-1 text-xs font-medium text-amber-600 hover:bg-amber-50" @click="toggle(r.id_user)">{{ r.is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                <button v-if="bisaReset" type="button" class="rounded-md px-2 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50" @click="bukaReset(r)">Reset PW</button>
                                <button v-if="bisaKelola" type="button" class="rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50" @click="hapus(r.id_user)">Hapus</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <div class="mt-3 flex items-center justify-between text-sm text-neutral-500">
                    <span>Total {{ list.total }} user</span>
                    <div class="flex items-center gap-2">
                        <button type="button" class="flex h-10 min-w-10 items-center justify-center rounded-md border border-neutral-200 px-3 disabled:opacity-40" :disabled="curPage <= 1" @click="curPage--; muat()">‹</button>
                        <span>{{ curPage }} / {{ list.last_page }}</span>
                        <button type="button" class="flex h-10 min-w-10 items-center justify-center rounded-md border border-neutral-200 px-3 disabled:opacity-40" :disabled="curPage >= list.last_page" @click="curPage++; muat()">›</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal form -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 p-4" @click.self="showForm = false">
        <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-emerald-800">{{ editRow ? 'Edit User' : 'Tambah User' }}</h3>
                <button type="button" class="text-neutral-400 hover:text-emerald-700" @click="showForm = false"><X class="h-5 w-5" /></button>
            </div>
            <div class="mt-4 space-y-3">
                <div v-if="is_super_admin">
                    <Label>Sekolah (Tenant)</Label>
                    <select v-model="f.id_sekolah" class="mt-1.5 w-full h-9 rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20">
                        <option value="">-- Sekolah saya --</option>
                        <option v-for="s in sekolah_list" :key="s.id_sekolah" :value="s.id_sekolah">{{ s.nama_sekolah }}</option>
                    </select>
                </div>
                <div><Label>Nama Lengkap</Label><Input v-model="f.nama_lengkap" class="mt-1.5" /><InputError :message="formErrors.nama_lengkap" /></div>
                <div><Label>Username</Label><Input v-model="f.username" class="mt-1.5" autocomplete="off" /><InputError :message="formErrors.username" /></div>
                <div>
                    <Label>Role</Label>
                    <select v-model="f.id_role" class="mt-1.5 w-full h-9 rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20">
                        <option value="">-- Pilih role --</option>
                        <option v-for="r in roles" :key="r.id_role" :value="r.id_role" class="capitalize">{{ r.nama_role }}</option>
                    </select>
                    <InputError :message="formErrors.id_role" />
                </div>
                <div>
                    <Label>Password {{ editRow ? '(kosongkan jika tidak diubah)' : '' }}</Label>
                    <Input v-model="f.password" type="password" class="mt-1.5" autocomplete="new-password" />
                    <InputError :message="formErrors.password" />
                </div>
                <div>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-neutral-700">
                        <input v-model="f.is_active" type="checkbox" class="h-4 w-4 rounded" /> Aktif
                    </label>
                    <InputError :message="formErrors.is_active" />
                </div>
            </div>
            <Button type="button" class="mt-4 w-full bg-emerald-600 hover:bg-emerald-700" @click="simpan">Simpan</Button>
        </div>
    </div>

    <!-- Modal reset password -->
    <div v-if="showReset && resetRow" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 p-4" @click.self="showReset = false">
        <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
            <div class="flex items-center justify-between">
                <h3 class="flex items-center gap-2 text-base font-bold text-emerald-800"><KeyRound class="h-4 w-4" /> Reset Password — {{ resetRow.username }}</h3>
                <button type="button" class="text-neutral-400 hover:text-emerald-700" @click="showReset = false"><X class="h-5 w-5" /></button>
            </div>
            <div class="mt-4 space-y-3">
                <div><Label>Password Baru (min 6)</Label><Input v-model="resetForm.password" type="password" class="mt-1.5" autocomplete="new-password" /><InputError :message="formErrors.password" /></div>
                <div><Label>Konfirmasi Password</Label><Input v-model="resetForm.password_confirmation" type="password" class="mt-1.5" autocomplete="new-password" /></div>
            </div>
            <Button type="button" class="mt-4 w-full bg-emerald-600 hover:bg-emerald-700" @click="prosesReset">Reset Password</Button>
        </div>
    </div>
</template>
