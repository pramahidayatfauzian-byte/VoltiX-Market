<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { User as UserIcon, Lock, ArrowRight, Eye, EyeOff, ShoppingCart, BarChart3, ShieldCheck, Sun, Moon, ScanBarcode, ReceiptText } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';

defineOptions({
    layout: undefined,
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const showPassword = ref(false);
const showLupa = ref(false);
const tahun = new Date().getFullYear();

const { isDark, toggleAppearance } = useAppearance();
function toggleTema() {
    toggleAppearance();
}
</script>

<template>
    <Head title="Login - VOLTIX" />

    <div class="flex min-h-svh bg-white">
        <!-- Kiri: branding (desktop saja) -->
        <div class="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-gradient-to-br from-emerald-700 via-emerald-600 to-teal-600 p-10 text-white lg:flex">
            <div aria-hidden="true" class="anim-hanyut pointer-events-none absolute -top-24 -right-24 size-72 rounded-full bg-white/10" />
            <div aria-hidden="true" class="anim-hanyut-lambat pointer-events-none absolute -bottom-32 -left-16 size-80 rounded-full bg-black/10" />
            <div aria-hidden="true" class="anim-hanyut-cepat pointer-events-none absolute top-1/3 -left-10 size-40 rounded-full bg-white/10 blur-xl" />

            <!-- Kartu melayang -->
            <div aria-hidden="true" class="anim-apung pointer-events-none absolute top-24 right-10 flex items-center gap-2 rounded-xl bg-white/15 px-3 py-2 text-xs shadow-lg backdrop-blur-sm">
                <ReceiptText class="h-4 w-4" />
                <span class="font-semibold">Struk #1201 • Rp 15.000</span>
            </div>
            <div aria-hidden="true" class="anim-apung-lambat pointer-events-none absolute right-16 bottom-40 flex items-center gap-2 rounded-xl bg-white/15 px-3 py-2 font-mono text-xs shadow-lg backdrop-blur-sm">
                <ScanBarcode class="h-4 w-4" />
                <span>899 8866 200223</span>
            </div>

            <div class="relative flex items-center gap-3">
                <span class="text-xl font-extrabold tracking-tight text-white">VOLTIX<span class="text-amber-300">.</span></span>
            </div>

            <div class="relative">
                <h2 class="anim-masuk max-w-md text-3xl leading-tight font-bold">
                    Kasir cepat, stok aman, laporan beres.
                </h2>
                <p class="anim-masuk mt-3 max-w-md text-sm text-white/80" style="animation-delay: 0.1s">
                    Sistem kasir modern untuk kantin & toko sekolah. Transaksi kilat, piutang tercatat, semua terkendali.
                </p>
                <ul class="mt-8 space-y-4 text-sm">
                    <li class="anim-masuk flex items-center gap-3" style="animation-delay: 0.2s">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/15"><ShoppingCart class="h-4 w-4" /></span>
                        Transaksi kasir + barcode dalam hitungan detik
                    </li>
                    <li class="anim-masuk flex items-center gap-3" style="animation-delay: 0.3s">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/15"><BarChart3 class="h-4 w-4" /></span>
                        Laporan, piutang & retur tercatat otomatis
                    </li>
                    <li class="anim-masuk flex items-center gap-3" style="animation-delay: 0.4s">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/15"><ShieldCheck class="h-4 w-4" /></span>
                        Multi tenant, aman per sekolah
                    </li>
                </ul>
            </div>

            <!-- Teks berjalan produk -->
            <div class="relative">
                <div aria-hidden="true" class="overflow-hidden rounded-xl bg-white/10 py-2 backdrop-blur-sm">
                    <div class="anim-jalan flex w-max text-xs font-medium whitespace-nowrap text-white/85">
                        <span v-for="n in 2" :key="n" class="flex items-center gap-6 pr-6">
                            <span>Indomie Goreng</span><span>•</span><span>Aqua 600ml</span><span>•</span><span>Teh Pucuk</span><span>•</span><span>Chitato</span><span>•</span><span>Beng-Beng</span><span>•</span><span>SilverQueen</span><span>•</span><span>Ultra Milk</span><span>•</span><span>Pocari Sweat</span><span>•</span>
                        </span>
                    </div>
                </div>
                <p class="mt-4 text-xs text-white/70">© {{ tahun }} VOLTIX • Sistem Kasir Modern</p>
            </div>
        </div>

        <!-- Kanan: form -->
        <div class="relative flex flex-1 items-center justify-center overflow-hidden bg-gradient-to-br from-emerald-50 via-white to-teal-50 px-4 pt-4 pb-14 sm:px-6 sm:pt-6 lg:p-6">
            <div aria-hidden="true" class="anim-hanyut pointer-events-none absolute -top-16 -left-16 size-64 rounded-full bg-emerald-300/40 blur-3xl" />
            <div aria-hidden="true" class="anim-hanyut-lambat pointer-events-none absolute -right-20 -bottom-20 size-72 rounded-full bg-teal-300/40 blur-3xl" />
            <div aria-hidden="true" class="anim-hanyut-cepat pointer-events-none absolute top-1/2 -right-10 size-40 rounded-full bg-amber-200/50 blur-3xl dark:bg-amber-900/20" />

            <!-- Bentuk dekoratif melayang (jelas terlihat di mode terang) -->
            <div aria-hidden="true" class="pointer-events-none absolute inset-0">
                <span class="anim-apung absolute top-[12%] left-[8%] size-20 rounded-full border-[3px] border-emerald-200/80 sm:size-24 dark:border-emerald-800/60" />
                <span class="anim-apung-lambat absolute right-[10%] bottom-[14%] size-14 rotate-12 rounded-2xl border-[3px] border-teal-200/80 sm:size-16 dark:border-teal-800/60" />
                <span class="anim-hanyut-cepat absolute top-[58%] left-[6%] hidden items-end gap-[3px] sm:flex" aria-hidden="true">
                    <i class="block h-8 w-[3px] rounded bg-emerald-300/70 not-italic dark:bg-emerald-700/60" />
                    <i class="block h-5 w-[2px] rounded bg-emerald-300/70 not-italic dark:bg-emerald-700/60" />
                    <i class="block h-9 w-[3px] rounded bg-emerald-400/70 not-italic dark:bg-emerald-600/60" />
                    <i class="block h-6 w-[2px] rounded bg-emerald-300/70 not-italic dark:bg-emerald-700/60" />
                    <i class="block h-8 w-[3px] rounded bg-teal-300/70 not-italic dark:bg-teal-700/60" />
                    <i class="block h-5 w-[2px] rounded bg-emerald-300/70 not-italic dark:bg-emerald-700/60" />
                    <i class="block h-7 w-[3px] rounded bg-emerald-400/70 not-italic dark:bg-emerald-600/60" />
                </span>
                <span class="anim-apung absolute top-[10%] right-[12%] hidden text-2xl font-bold text-emerald-200 select-none sm:block dark:text-emerald-900">+</span>
                <span class="anim-apung-lambat absolute bottom-[10%] left-[38%] hidden text-xl font-bold text-teal-200 select-none sm:block dark:text-teal-900">+</span>
            </div>
            <button
                type="button"
                class="absolute top-4 right-4 flex h-9 w-9 items-center justify-center rounded-full border border-emerald-100 bg-white text-neutral-500 shadow-sm transition hover:bg-emerald-50 hover:text-emerald-700"
                :aria-label="isDark ? 'Mode terang' : 'Mode gelap'"
                :title="isDark ? 'Mode terang' : 'Mode gelap'"
                @click="toggleTema()"
            >
                <Sun v-if="isDark" class="h-5 w-5" />
                <Moon v-else class="h-5 w-5" />
            </button>
            <div class="anim-masuk-kartu relative w-full max-w-md overflow-hidden rounded-2xl border border-emerald-100 bg-white shadow-lg shadow-emerald-100" style="animation-delay: 0.15s">
                <!-- Kilau menyapu kartu -->
                <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden rounded-2xl">
                    <span class="anim-kilat absolute inset-y-0 w-1/3 -skew-x-12 bg-gradient-to-r from-transparent via-emerald-200/50 to-transparent dark:via-emerald-800/40" />
                </div>
                <!-- Header -->
                <div class="flex flex-col items-center px-8 pt-8 pb-6 text-center">
                    <p class="text-sm font-semibold tracking-widest text-emerald-600 uppercase dark:text-emerald-300">
                        Selamat Datang
                    </p>
                    <div class="mt-1 text-4xl font-extrabold tracking-tight">
                        <span class="bg-gradient-to-r from-emerald-600 to-teal-500 bg-clip-text text-transparent dark:from-emerald-300 dark:to-teal-200">VOLTIX</span><span class="text-amber-400">.</span>
                    </div>
                    <p class="mt-2 text-sm text-neutral-500">
                        Masuk untuk mulai kasir
                    </p>
                </div>

                <div class="px-8 pb-8">
                    <div
                        v-if="status"
                        class="mb-4 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-center text-sm font-medium text-green-700"
                    >
                        {{ status }}
                    </div>

                    <Form
                        v-bind="store.form()"
                        :reset-on-success="['password']"
                        v-slot="{ errors, processing }"
                        class="flex flex-col gap-4"
                    >
                        <div class="grid gap-2">
                            <Label for="username">Username</Label>
                            <div class="relative">
                                <UserIcon class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-emerald-600" />
                                <Input
                                    id="username"
                                    type="text"
                                    name="username"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    placeholder="Masukkan username"
                                    class="pl-9"
                                />
                            </div>
                            <InputError :message="errors.username" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password">Password</Label>
                            <div class="relative">
                                <Lock class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-emerald-600" />
                                <Input
                                    id="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    name="password"
                                    required
                                    autocomplete="current-password"
                                    placeholder="Masukkan password"
                                    class="pr-10 pl-9"
                                />
                                <button
                                    type="button"
                                    class="absolute top-1/2 right-2.5 -translate-y-1/2 rounded-md p-1 text-neutral-400 transition hover:bg-neutral-100 hover:text-emerald-700"
                                    :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                    :title="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                    @click="showPassword = !showPassword"
                                >
                                    <EyeOff v-if="showPassword" class="h-4 w-4" />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </div>
                            <InputError :message="errors.password" />
                        </div>

                        <div class="flex items-center justify-between pt-1">
                            <Label for="remember" class="flex cursor-pointer items-center space-x-2 text-sm font-normal text-neutral-600">
                                <Checkbox id="remember" name="remember" />
                                <span>Ingat saya</span>
                            </Label>
                            <button
                                type="button"
                                class="text-sm font-medium text-emerald-700 hover:underline"
                                @click="showLupa = !showLupa"
                            >
                                Lupa password?
                            </button>
                        </div>
                        <div
                            v-if="showLupa"
                            class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
                        >
                            Akun dikelola oleh administrator. Silakan hubungi admin sekolah untuk reset password.
                        </div>

                        <Button
                            type="submit"
                            class="mt-2 w-full bg-emerald-600 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700"
                            :disabled="processing"
                            data-test="login-button"
                        >
                            <Spinner v-if="processing" />
                            <template v-else>
                                LOGIN
                                <ArrowRight class="ml-1 h-4 w-4" />
                            </template>
                        </Button>
                    </Form>

                    <p class="pt-5 text-center text-xs text-neutral-400">
                        © {{ tahun }} VOLTIX v1.0
                    </p>
                </div>
            </div>

            <!-- Teks berjalan produk (tampil di HP; desktop sudah ada di panel kiri) -->
            <div aria-hidden="true" class="anim-masuk absolute inset-x-0 bottom-0 overflow-hidden border-t border-emerald-100/70 bg-white/60 py-2 backdrop-blur-sm lg:hidden dark:border-neutral-800 dark:bg-neutral-950/60" style="animation-delay: 0.3s">
                <div class="anim-jalan flex w-max text-[11px] font-medium whitespace-nowrap text-emerald-700 dark:text-emerald-300">
                    <span v-for="n in 2" :key="n" class="flex items-center gap-5 pr-5">
                        <span>Indomie Goreng</span><span>•</span><span>Aqua 600ml</span><span>•</span><span>Teh Pucuk</span><span>•</span><span>Chitato</span><span>•</span><span>Beng-Beng</span><span>•</span><span>Kasir kilat</span><span>•</span>
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes login-apung {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-12px); }
}
@keyframes login-hanyut {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(28px, -28px) scale(1.08); }
}
@keyframes login-masuk {
    from { opacity: 0; transform: translateY(16px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes login-masuk-kartu {
    from { opacity: 0; transform: translateY(24px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
@keyframes login-jalan {
    from { transform: translateX(0); }
    to { transform: translateX(-50%); }
}
@keyframes login-denyut {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.07); }
}
@keyframes login-kilat {
    0% { left: -40%; }
    45% { left: 130%; }
    100% { left: 130%; }
}

.anim-apung { animation: login-apung 4s ease-in-out infinite; }
.anim-apung-lambat { animation: login-apung 5.5s ease-in-out 0.8s infinite; }
.anim-hanyut { animation: login-hanyut 9s ease-in-out infinite; }
.anim-hanyut-lambat { animation: login-hanyut 12s ease-in-out 1.5s infinite; }
.anim-hanyut-cepat { animation: login-hanyut 7s ease-in-out 0.5s infinite; }
.anim-masuk { animation: login-masuk 0.6s ease-out both; }
.anim-masuk-kartu { animation: login-masuk-kartu 0.55s ease-out both; }
.anim-logo { animation: login-denyut 3s ease-in-out infinite; }
.anim-kilat { animation: login-kilat 5.5s ease-in-out infinite; }
.anim-jalan { animation: login-jalan 24s linear infinite; }

@media (prefers-reduced-motion: reduce) {
    .anim-apung,
    .anim-apung-lambat,
    .anim-hanyut,
    .anim-hanyut-lambat,
    .anim-hanyut-cepat,
    .anim-masuk,
    .anim-masuk-kartu,
    .anim-logo,
    .anim-kilat,
    .anim-jalan {
        animation: none;
    }
}
</style>
