<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, Settings } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { konfirmasi } from '@/composables/useConfirm';
import UserInfo from '@/components/UserInfo.vue';
import { logout } from '@/routes';
import type { User } from '@/types';

type Props = {
    user: User;
};

const props = defineProps<Props>();
const page = usePage();
const role = computed(() => ((props.user as any)?.role ?? page.props.auth?.user?.role ?? '') as string);
const bisaPengaturan = computed(() => ['super admin', 'developer'].includes(role.value));

async function konfirmasiKeluar() {
    if (!(await konfirmasi({ judul: 'Keluar dari VOLTIX', pesan: 'Sesi Anda akan diakhiri. Yakin mau keluar?', teksYa: 'Ya, keluar', teksBatal: 'Batal', varian: 'utama', ikon: 'keluar' }))) return;
    router.flushAll();
    router.post(logout());
}
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <template v-if="bisaPengaturan">
        <DropdownMenuSeparator />
        <DropdownMenuGroup>
            <DropdownMenuItem :as-child="true">
                <Link class="block w-full cursor-pointer" href="/pengaturan" prefetch>
                    <Settings class="mr-2 h-4 w-4" />
                    Settings
                </Link>
            </DropdownMenuItem>
        </DropdownMenuGroup>
    </template>
    <DropdownMenuSeparator />
    <DropdownMenuItem class="cursor-pointer" @click="konfirmasiKeluar" data-test="logout-button">
        <LogOut class="mr-2 h-4 w-4" />
        Log out
    </DropdownMenuItem>
</template>
