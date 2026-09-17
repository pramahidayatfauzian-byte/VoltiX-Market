<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Zap, User as UserIcon, Lock, ArrowRight, Eye, EyeOff, ShoppingCart, BarChart3, ShieldCheck, Sun, Moon } from '@lucide/vue';
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
                    Kasir cepat, stok aman, laporan beres.
                </h2>
                <p class="mt-3 max-w-md text-sm text-white/80">
                    Sistem kasir modern untuk kantin & toko sekolah. Transaksi kilat, piutang tercatat, semua terkendali.
                </p>
                <ul class="mt-8 space-y-4 text-sm">
                    <li class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/15"><ShoppingCart class="h-4 w-4" /></span>
                        Transaksi kasir + barcode dalam hitungan detik
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/15"><BarChart3 class="h-4 w-4" /></span>
                        Laporan, piutang & retur tercatat otomatis
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/15"><ShieldCheck class="h-4 w-4" /></span>
                        Multi tenant, aman per sekolah
                    </li>
                </ul>
            </div>

            <p class="relative text-xs text-white/70">© {{ tahun }} VOLTIX • Sistem Kasir Modern</p>
        </div>

        <!-- Kanan: form -->
        <div class="relative flex flex-1 items-center justify-center bg-gradient-to-br from-emerald-50 via-white to-teal-50 p-4 sm:p-6">
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
            <div class="w-full max-w-md overflow-hidden rounded-2xl border border-emerald-100 bg-white shadow-lg shadow-emerald-100">
                <!-- Header -->
                <div class="flex flex-col items-center px-8 pt-10 pb-6 text-center">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-md shadow-emerald-200">
                        <Zap class="h-7 w-7" />
                    </div>
                    <h1 class="mt-4 text-xl font-bold tracking-tight text-emerald-700">
                        VOLTIX
                    </h1>
                    <p class="mt-1 text-sm text-neutral-500">
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
        </div>
    </div>
</template>
