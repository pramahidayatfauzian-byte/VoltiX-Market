<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onMounted } from 'vue';

// POS SEKOLAH: halaman profil bawaan (name/email) tidak dipakai karena
// db_zian memakai tb_user. Alihkan ke halaman Pengaturan.
onMounted(() => {
    router.visit('/pengaturan');
});
</script>

<template>
    <Head title="Profile settings" />
    <p class="text-sm text-neutral-500">Mengalihkan ke Pengaturan...</p>
</template>
