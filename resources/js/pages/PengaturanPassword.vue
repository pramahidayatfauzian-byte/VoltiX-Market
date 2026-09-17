<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Eye, EyeOff, KeyRound, ShieldCheck, ShoppingCart, BarChart3, Zap } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: undefined,
});

defineProps<{
    me: { nama_lengkap: string | null; username: string | null };
}>();

const page = usePage();
const flash = computed(() => page.props.flash as Record<string, unknown>);
const formErrors = computed(() => (page.props.errors ?? {}) as Record<string, string>);
const successMsg = computed(() => flash.value.success as string | undefined);

const pwForm = reactive({ current_password: '', password: '', password_confirmation: '' });
const processing = ref(false);

const showCurrent = ref(false);
const showNew = ref(false);
const tahun = new Date().getFullYear();

function simpanPassword() {
    processing.value = true;
    router.post('/pengaturan/password', { ...pwForm }, {
        preserveScroll: true,
        onSuccess: () => {
            pwForm.current_password = '';
            pwForm.password = '';
            pwForm.password_confirmation = '';
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <Head title="Ganti Password - VOLTIX" />

    <div class="flex min-h-svh bg-white">
        <!-- Kiri: branding ala login (desktop saja) -->
        <div class="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-gradient-to-br from-emerald-700 via-emerald-600 to-teal-600 p-10 text-white lg:flex">
            <div class="pointer-events-none absolute -top-24 -right-24 size-72 rounded-full bg-white/10" />
            <div class="pointer-events-none absolute -bottom-32 -left-16 size-80 rounded-full bg-black/10" />

            <div class="relative flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/15">
                    <Zap class="h-6 w-6" />
                </span>
                <span class="text-lg font-bold tracking-tight">VOLTIX</span>
            </div>

            <div class="relative">
                <h2 class="max-w-md text-3xl leading-tight font-bold">
                    Keamanan akun terjaga.
                </h2>
                <p class="mt-3 max-w-md text-sm text-white/80">
                    Ganti password secara berkala untuk melindungi data kasir, stok, dan laporan sekolah Anda.
                </p>
                <ul class="mt-8 space-y-4 text-sm">
                    <li class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/15"><ShieldCheck class="h-4 w-4" /></span>
                        Password minimal 6 karakter, jangan bagikan ke siapa pun
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/15"><ShoppingCart class="h-4 w-4" /></span>
                        Tetap login setelah ganti password di perangkat ini
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/15"><BarChart3 class="h-4 w-4" /></span>
                        Semua perubahan tercatat di audit log
                    </li>
                </ul>
            </div>

            <p class="relative text-xs text-white/70">© {{ tahun }} VOLTIX • Sistem Kasir Modern</p>
        </div>

        <!-- Kanan: form dengan background login -->
        <div class="relative flex flex-1 items-center justify-center bg-gradient-to-br from-emerald-50 via-white to-teal-50 p-4 sm:p-6">
            <div class="w-full max-w-md rounded-2xl border border-emerald-100 bg-white p-5 shadow-xl shadow-emerald-100/50 sm:p-6">
                <Link href="/pengaturan" class="inline-flex items-center gap-1 text-sm font-medium text-emerald-700 hover:text-emerald-800">
                    <ArrowLeft class="h-4 w-4" /> Kembali ke Pengaturan
                </Link>

                <div class="mt-3 flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-600 to-teal-600 text-white">
                        <KeyRound class="h-5 w-5" />
                    </span>
                    <div>
                        <h1 class="text-lg font-bold tracking-tight text-neutral-900">Ganti Password</h1>
                        <p class="text-sm text-neutral-500">{{ me.nama_lengkap }} ({{ me.username }})</p>
                    </div>
                </div>

                <div v-if="successMsg" class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">
                    {{ successMsg }}
                </div>

                <div class="mt-4 space-y-3">
                    <div>
                        <Label>Password Saat Ini</Label>
                        <div class="relative mt-1.5">
                            <Input v-model="pwForm.current_password" :type="showCurrent ? 'text' : 'password'" autocomplete="current-password" class="pr-11" />
                            <button type="button" class="absolute top-1/2 right-2 -translate-y-1/2 rounded-md p-1.5 text-neutral-400 hover:bg-emerald-50 hover:text-emerald-700" @click="showCurrent = !showCurrent" title="Lihat / sembunyi">
                                <Eye v-if="!showCurrent" class="h-4 w-4" />
                                <EyeOff v-else class="h-4 w-4" />
                            </button>
                        </div>
                        <InputError :message="formErrors.current_password" />
                    </div>
                    <div>
                        <Label>Password Baru (min 6)</Label>
                        <div class="relative mt-1.5">
                            <Input v-model="pwForm.password" :type="showNew ? 'text' : 'password'" autocomplete="new-password" class="pr-11" />
                            <button type="button" class="absolute top-1/2 right-2 -translate-y-1/2 rounded-md p-1.5 text-neutral-400 hover:bg-emerald-50 hover:text-emerald-700" @click="showNew = !showNew" title="Lihat / sembunyi">
                                <Eye v-if="!showNew" class="h-4 w-4" />
                                <EyeOff v-else class="h-4 w-4" />
                            </button>
                        </div>
                        <InputError :message="formErrors.password" />
                    </div>
                    <div>
                        <Label>Konfirmasi Password Baru</Label>
                        <Input v-model="pwForm.password_confirmation" :type="showNew ? 'text' : 'password'" class="mt-1.5" autocomplete="new-password" />
                    </div>
                </div>
                <Button type="button" class="mt-5 w-full bg-emerald-600 hover:bg-emerald-700" :disabled="processing" @click="simpanPassword()">
                    {{ processing ? 'Menyimpan...' : 'Ubah Password' }}
                </Button>
            </div>
        </div>
    </div>
</template>
