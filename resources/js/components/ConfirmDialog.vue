<script setup lang="ts">
import { CheckCircle, LogOut, TriangleAlert } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { jawabKonfirmasi, useConfirmState } from '@/composables/useConfirm';

const state = useConfirmState();

function tutup() {
    jawabKonfirmasi(false);
}
</script>

<template>
    <Dialog :open="state.terbuka" @update:open="(v) => { if (!v) tutup(); }">
        <DialogContent
            class="anim-confirm-masuk max-w-[calc(100%-2rem)] overflow-hidden sm:max-w-sm"
            :class="state.varian === 'danger' ? 'drop-shadow-[0_0_28px_rgba(239,68,68,0.25)]' : 'drop-shadow-[0_0_28px_rgba(16,185,129,0.25)]'"
        >
            <div aria-hidden="true" class="pointer-events-none absolute inset-x-10 top-0 h-1 rounded-b-full bg-gradient-to-r from-transparent via-emerald-400/80 to-transparent" />
            <DialogHeader class="flex flex-row items-start gap-3 text-left">
                <span
                    class="anim-confirm-apung relative flex h-12 w-12 shrink-0 items-center justify-center overflow-visible rounded-2xl text-white shadow-lg transition"
                    :class="state.varian === 'danger' ? 'bg-gradient-to-br from-red-500 to-orange-500 shadow-red-500/30 ring-4 ring-red-100 dark:ring-red-950' : 'bg-gradient-to-br from-emerald-500 to-teal-500 shadow-emerald-500/30 ring-4 ring-emerald-100 dark:ring-emerald-950'"
                >
                    <span v-if="state.ikon === 'keluar'" aria-hidden="true" class="absolute inset-0 animate-ping rounded-2xl bg-red-400/30 [animation-duration:1.8s]" />
                    <span aria-hidden="true" class="anim-confirm-kilau pointer-events-none absolute inset-y-0 w-1/2 -skew-x-12 bg-white/25" />
                    <LogOut v-if="state.ikon === 'keluar'" class="anim-keluar relative h-6 w-6" />
                    <CheckCircle v-else-if="state.ikon === 'berhasil'" class="relative h-6 w-6" />
                    <TriangleAlert v-else class="relative h-6 w-6" />
                </span>
                <div class="min-w-0 pt-0.5">
                    <DialogTitle class="text-base font-extrabold tracking-tight">{{ state.judul }}</DialogTitle>
                    <DialogDescription class="mt-1">{{ state.pesan }}</DialogDescription>
                </div>
            </DialogHeader>
            <DialogFooter class="grid grid-cols-2 gap-2 sm:flex sm:justify-end">
                <Button type="button" variant="outline" class="anim-confirm-masuk h-11 rounded-xl transition active:scale-95" @click="jawabKonfirmasi(false)">
                    {{ state.teksBatal }}
                </Button>
                <Button
                    type="button"
                    class="anim-confirm-masuk relative h-11 overflow-hidden rounded-xl text-white shadow-md transition active:scale-95"
                    style="animation-delay: 0.08s"
                    :class="state.varian === 'danger' ? 'bg-gradient-to-b from-red-500 to-red-600 shadow-red-500/30 hover:brightness-110' : 'bg-gradient-to-b from-emerald-500 to-teal-600 shadow-emerald-500/30 hover:brightness-110'"
                    @click="jawabKonfirmasi(true)"
                >
                    <span aria-hidden="true" class="anim-confirm-kilau pointer-events-none absolute inset-y-0 w-1/3 -skew-x-12 bg-white/25" />
                    <span class="relative">{{ state.teksYa }}</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
@keyframes confirm-masuk {
    from {
        opacity: 0;
        transform: scale(0.92) translateY(12px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}
@keyframes confirm-kilau {
    0% { left: -50%; }
    45%, 100% { left: 130%; }
}
@keyframes keluar-geser {
    0%, 100% { transform: translateX(0); }
    50% { transform: translateX(3px); }
}
@keyframes confirm-apung {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-4px); }
}
.anim-confirm-masuk {
    animation: confirm-masuk 0.28s cubic-bezier(0.34, 1.4, 0.64, 1) both;
}
.anim-confirm-apung {
    animation: confirm-apung 2.6s ease-in-out infinite;
}
.anim-confirm-kilau {
    animation: confirm-kilau 2.8s ease-in-out infinite;
}
.anim-keluar {
    animation: keluar-geser 1.2s ease-in-out infinite;
}
@media (prefers-reduced-motion: reduce) {
    .anim-confirm-masuk,
    .anim-confirm-kilau,
    .anim-confirm-apung,
    .anim-keluar {
        animation: none;
    }
}
</style>
