<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ChevronsUpDown, LogOut } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { konfirmasi } from '@/composables/useConfirm';
import UserInfo from '@/components/UserInfo.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { logout } from '@/routes';

const page = usePage();
const user = computed(() => page.props.auth.user);
const namaUser = computed(
    () => user.value?.nama_lengkap || user.value?.name || user.value?.username || 'User',
);
const peranUser = computed(() => (user.value?.role as string | undefined) ?? '');
const { isMobile, state } = useSidebar();

async function konfirmasiKeluar() {
    if (!(await konfirmasi({ judul: 'Keluar dari VOLTIX', pesan: 'Sesi Anda akan diakhiri. Yakin mau keluar?', teksYa: 'Ya, keluar', teksBatal: 'Batal', varian: 'utama', ikon: 'keluar' }))) return;
    router.flushAll();
    router.post(logout());
}
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <div class="flex items-center gap-1">
                <div class="min-w-0 flex-1">
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        data-test="sidebar-menu-button"
                    >
                        <UserInfo :user="user" />
                        <span class="grid min-w-0 flex-1 text-left text-sm leading-tight group-data-[collapsible=icon]:hidden">
                            <span class="truncate font-semibold text-white">{{ namaUser }}</span>
                            <span class="truncate text-xs text-white/60 capitalize">{{ peranUser }}</span>
                        </span>
                        <ChevronsUpDown class="ml-auto size-4 shrink-0 text-white/70 group-data-[collapsible=icon]:hidden" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                    :side="
                        isMobile
                            ? 'bottom'
                            : state === 'collapsed'
                              ? 'left'
                              : 'bottom'
                    "
                    align="end"
                    :side-offset="4"
                >
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
                </div>
                <button
                    type="button"
                    title="Keluar"
                    aria-label="Keluar"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-white/70 transition hover:bg-red-500/25 hover:text-white group-data-[collapsible=icon]:hidden"
                    @click="konfirmasiKeluar()"
                >
                    <LogOut class="h-4 w-4" />
                </button>
            </div>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
