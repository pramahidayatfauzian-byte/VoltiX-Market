<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
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
        <DialogContent class="max-w-[calc(100%-2rem)] sm:max-w-sm">
            <DialogHeader class="flex flex-row items-start gap-3 text-left">
                <span
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                    :class="state.varian === 'danger' ? 'bg-red-100 text-red-600' : 'bg-emerald-100 text-emerald-700'"
                >
                    <TriangleAlert class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <DialogTitle>{{ state.judul }}</DialogTitle>
                    <DialogDescription class="mt-1">{{ state.pesan }}</DialogDescription>
                </div>
            </DialogHeader>
            <DialogFooter class="grid grid-cols-2 gap-2 sm:flex sm:justify-end">
                <Button type="button" variant="outline" class="h-11" @click="jawabKonfirmasi(false)">
                    {{ state.teksBatal }}
                </Button>
                <Button
                    type="button"
                    class="h-11"
                    :class="state.varian === 'danger' ? 'bg-red-600 hover:bg-red-700' : 'bg-emerald-600 hover:bg-emerald-700'"
                    @click="jawabKonfirmasi(true)"
                >
                    {{ state.teksYa }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
