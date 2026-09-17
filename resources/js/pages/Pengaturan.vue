<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, DatabaseBackup, KeyRound, LayoutGrid, School, ScrollText, ShieldCheck, User, Users } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pengaturan', href: '/pengaturan' }],
    },
});

type SekolahOpt = { id_sekolah: number; nama_sekolah: string | null };

const props = defineProps<{
    backup?: { database: string; tabel: number; ukuran: string } | null;
    sekolah?: { id_sekolah: number; nama_sekolah: string | null } | null;
    sekolah_list: SekolahOpt[];
    is_super_admin: boolean;
    is_super_admin_role?: boolean;
    role_saya?: string | null;
    bisa_kelola_sekolah?: boolean;
    target: {
        id_sekolah: number;
        kode_sekolah: string | null;
        nama_sekolah: string | null;
        alamat_sekolah: string | null;
        website: string | null;
        logo_url: string | null;
    };
    me: { nama_lengkap: string | null; username: string | null };
}>();

const page = usePage();
const flash = computed(() => page.props.flash as Record<string, unknown>);
const formErrors = computed(() => (page.props.errors ?? {}) as Record<string, string>);
const successMsg = computed(() => flash.value.success as string | undefined);

const pilihSekolah = ref(props.target.id_sekolah);
function gantiTarget() {
    router.get('/pengaturan', { id_sekolah: pilihSekolah.value }, { preserveState: false });
}

const sekForm = reactive({
    nama_sekolah: props.target.nama_sekolah ?? '',
    alamat_sekolah: props.target.alamat_sekolah ?? '',
    website: props.target.website ?? '',
});
const logoFile = ref<File | null>(null);
const logoPreview = ref<string | null>(props.target.logo_url);
const logoError = ref<string>('');
const menyimpan = ref(false);
const mengunduhBackup = ref(false);

function pilihLogo(e: Event) {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    logoError.value = '';
    if (!file) {
        logoFile.value = null;
        logoPreview.value = props.target.logo_url;
        return;
    }
    const tipeValid = ['image/jpeg', 'image/png', 'image/webp'];
    if (!tipeValid.includes(file.type)) {
        logoError.value = 'Format logo harus jpg, png, atau webp.';
        input.value = '';
        logoFile.value = null;
        logoPreview.value = props.target.logo_url;
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        logoError.value = 'Ukuran logo maksimal 2MB.';
        input.value = '';
        logoFile.value = null;
        logoPreview.value = props.target.logo_url;
        return;
    }
    logoFile.value = file;
    logoPreview.value = URL.createObjectURL(file);
}

function simpanSekolah() {
    if (menyimpan.value) return;
    logoError.value = '';
    const fd = new FormData();
    fd.append('nama_sekolah', sekForm.nama_sekolah);
    fd.append('alamat_sekolah', sekForm.alamat_sekolah ?? '');
    fd.append('website', sekForm.website ?? '');
    if (logoFile.value) fd.append('logo', logoFile.value);
    menyimpan.value = true;
    router.post(`/pengaturan/sekolah/${props.target.id_sekolah}`, fd, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            logoFile.value = null;
        },
        onError: (errs) => {
            if (errs.logo) logoError.value = String(errs.logo);
        },
        onFinish: () => {
            menyimpan.value = false;
        },
    });
}

function unduhBackup() {
    if (mengunduhBackup.value) return;
    mengunduhBackup.value = true;
    window.location.href = '/pengaturan/backup';
    setTimeout(() => { mengunduhBackup.value = false; }, 3000);
}
</script>

<template>
    <Head title="Pengaturan" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-emerald-800">Pengaturan</h1>
            <p class="mt-0.5 text-sm text-neutral-500">Profil sekolah & keamanan akun</p>
        </div>

        <div v-if="successMsg" class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">{{ successMsg }}</div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <!-- Kiri: Profil sekolah (developer, super admin, & admin) -->
            <div v-if="props.bisa_kelola_sekolah ?? props.is_super_admin" class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm xl:col-span-2">
                <h2 class="flex items-center gap-2 text-sm font-bold text-emerald-800">
                    <School class="h-4 w-4" /> Profil Sekolah
                </h2>
                <div v-if="is_super_admin" class="mt-3">
                    <Label>Sekolah yang diatur</Label>
                    <div class="mt-1.5 flex gap-2">
                        <select v-model="pilihSekolah" class="flex-1 h-9 rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20">
                            <option v-for="s in sekolah_list" :key="s.id_sekolah" :value="s.id_sekolah">{{ s.nama_sekolah }}</option>
                        </select>
                        <Button type="button" variant="outline" @click="gantiTarget()">Pilih</Button>
                    </div>
                </div>
                <div class="mt-3 space-y-3">
                    <div>
                        <Label>Kode Sekolah</Label>
                        <Input :model-value="target.kode_sekolah ?? '-'" disabled class="mt-1.5 bg-neutral-50" />
                    </div>
                    <div>
                        <Label>Nama Sekolah</Label>
                        <Input v-model="sekForm.nama_sekolah" class="mt-1.5" />
                        <InputError :message="formErrors.nama_sekolah" />
                    </div>
                    <div>
                        <Label>Alamat</Label>
                        <Input v-model="sekForm.alamat_sekolah" class="mt-1.5" />
                        <InputError :message="formErrors.alamat_sekolah" />
                    </div>
                    <div>
                        <Label>Website</Label>
                        <Input v-model="sekForm.website" class="mt-1.5" placeholder="https://..." />
                        <InputError :message="formErrors.website" />
                    </div>
                    <div>
                        <Label>Logo Sekolah (jpg/png/webp, maks 2MB)</Label>
                        <div class="mt-1.5 flex flex-col gap-3 sm:flex-row sm:items-center">
                            <img v-if="logoPreview" :src="logoPreview" alt="Logo" class="h-16 w-16 shrink-0 rounded-lg border border-neutral-200 object-cover" />
                            <input type="file" accept="image/jpeg,image/png,image/webp" class="w-full min-w-0 text-sm text-neutral-500 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-600 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-emerald-700" @change="pilihLogo" />
                        </div>
                        <InputError :message="logoError || formErrors.logo" />
                    </div>
                </div>
                <Button type="button" class="mt-4 w-full bg-emerald-600 hover:bg-emerald-700" :disabled="menyimpan" @click="simpanSekolah()">{{ menyimpan ? 'Menyimpan...' : 'Simpan Profil' }}</Button>
            </div>

            <!-- Kanan: tumpukan kartu akun + keamanan + tips + menu cepat -->
            <div class="flex flex-col gap-4">
                <!-- 1. Informasi Akun -->
                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                    <h2 class="flex items-center gap-2 text-sm font-bold text-emerald-800">
                        <User class="h-4 w-4" /> Informasi Akun
                    </h2>
                    <div class="mt-3 flex items-center gap-3">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-emerald-600 to-teal-600 text-base font-bold text-white">
                            {{ (me.nama_lengkap ?? me.username ?? '?').trim().charAt(0).toUpperCase() }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-neutral-900">{{ me.nama_lengkap }}</p>
                            <p class="truncate text-xs text-neutral-400">@{{ me.username }}</p>
                        </div>
                    </div>
                    <dl class="mt-3 space-y-1.5 border-t border-neutral-100 pt-3 text-sm">
                        <div class="flex justify-between gap-2">
                            <dt class="text-neutral-400">Peran</dt>
                            <dd class="font-semibold text-neutral-800 capitalize">{{ role_saya ?? '-' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-neutral-400">Sekolah aktif</dt>
                            <dd class="max-w-45 truncate font-semibold text-neutral-800">{{ sekolah?.nama_sekolah ?? target.nama_sekolah ?? '-' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-neutral-400">Kode sekolah</dt>
                            <dd class="font-semibold text-neutral-800">{{ target.kode_sekolah ?? '-' }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Keamanan akun → kartu menu ke halaman baru -->
                <Link href="/pengaturan/password" class="group flex h-fit items-center gap-4 rounded-xl border border-neutral-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md">
                    <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-600 to-teal-600 text-white shadow-sm">
                        <KeyRound class="h-6 w-6" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-emerald-800">Keamanan Akun</span>
                        <span class="mt-1 block text-sm text-neutral-600">Ganti password akun Anda di halaman khusus yang aman.</span>
                    </span>
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-emerald-200 text-emerald-700 transition group-hover:bg-emerald-600 group-hover:text-white">
                        <ArrowRight class="h-4 w-4" />
                    </span>
                </Link>

                <!-- 2. Tips Keamanan -->
                <div class="rounded-xl border border-amber-100 bg-amber-50 p-4 shadow-sm">
                    <h2 class="flex items-center gap-2 text-sm font-bold text-amber-800">
                        <ShieldCheck class="h-4 w-4" /> Tips Keamanan
                    </h2>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-900/80">
                        <li>Ganti password minimal 6 karakter secara berkala.</li>
                        <li>Jangan bagikan akun kasir ke orang lain.</li>
                        <li>Logout setelah selesai shift di komputer bersama.</li>
                    </ul>
                </div>

                <!-- 3. Menu Cepat -->
                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                    <h2 class="flex items-center gap-2 text-sm font-bold text-emerald-800">
                        <LayoutGrid class="h-4 w-4" /> Menu Cepat
                    </h2>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <Link href="/user" class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-sm font-medium text-neutral-700 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800">
                            <Users class="h-4 w-4 text-emerald-600" /> Kelola User
                        </Link>
                        <Link href="/audit" class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-sm font-medium text-neutral-700 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800">
                            <ScrollText class="h-4 w-4 text-emerald-600" /> Audit Log
                        </Link>
                        <Link href="/pengaturan/password" class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-sm font-medium text-neutral-700 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800">
                            <KeyRound class="h-4 w-4 text-emerald-600" /> Ganti Password
                        </Link>
                        <button type="button" :disabled="mengunduhBackup" class="flex min-h-11 items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-left text-sm font-medium text-neutral-700 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800 disabled:opacity-50" @click="unduhBackup()">
                            <DatabaseBackup class="h-4 w-4 text-emerald-600" /> {{ mengunduhBackup ? 'Menyiapkan...' : 'Backup DB' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Backup database (developer) -->
            <div v-if="is_super_admin" class="rounded-xl border border-teal-100 bg-teal-50 p-4 shadow-sm xl:col-span-3">
                <h2 class="flex items-center gap-2 text-sm font-bold text-emerald-800">
                    <DatabaseBackup class="h-4 w-4" /> Backup Database
                </h2>
                <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-neutral-500">
                        Unduh salinan <span class="font-semibold text-neutral-700">{{ backup?.database }}</span>
                        <span v-if="backup">({{ backup.tabel }} tabel, {{ backup.ukuran }})</span>
                        dalam format .sql. Berisi data semua tenant — simpan di tempat aman.
                    </p>
                    <Button type="button" class="h-11 shrink-0 bg-emerald-600 hover:bg-emerald-700" :disabled="mengunduhBackup" @click="unduhBackup()">
                        <DatabaseBackup class="mr-1 h-4 w-4" /> {{ mengunduhBackup ? 'Menyiapkan...' : 'Unduh Backup' }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
