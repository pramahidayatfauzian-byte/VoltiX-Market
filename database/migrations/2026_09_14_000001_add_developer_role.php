<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambah role 'developer' (akses global, menggantikan peran super admin lama).
     * - Super admin global (id_sekolah NULL) dikonversi menjadi developer agar hak aksesnya tetap.
     * - Setiap sekolah yang belum punya super admin dibuatkan akun (superadmin1, superadmin2, ...)
     *   dengan password default 'password' — segera ganti setelah login.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `roles` MODIFY `nama_role` ENUM('super admin','admin','kasir','developer','') NULL");
        }
        // SQLite/Postgres: kolom enum tersimpan sebagai varchar/text tanpa check,
        // sehingga nilai baru langsung bisa dipakai tanpa ALTER.

        $devId = DB::table('roles')->where('nama_role', 'developer')->value('id_role');
        if (! $devId) {
            $devId = DB::table('roles')->insertGetId(['nama_role' => 'developer']);
        }

        $saId = DB::table('roles')->where('nama_role', 'super admin')->value('id_role');

        // 1) Super admin global -> developer (akses penuh tetap terjaga).
        if ($saId) {
            DB::table('tb_user')
                ->where('id_role', $saId)
                ->whereNull('id_sekolah')
                ->whereNull('deleted_at')
                ->update(['id_role' => $devId]);
        }

        // 2) Pastikan setiap sekolah punya 1 super admin (per-sekolah).
        if ($saId) {
            $sekolahList = DB::table('tb_sekolah')->orderBy('id_sekolah')->get(['id_sekolah']);
            $n = 0;
            foreach ($sekolahList as $sekolah) {
                $ada = DB::table('tb_user')
                    ->where('id_role', $saId)
                    ->where('id_sekolah', $sekolah->id_sekolah)
                    ->whereNull('deleted_at')
                    ->exists();
                if ($ada) {
                    continue;
                }
                $n++;
                $username = 'superadmin'.$n;
                while (DB::table('tb_user')->where('username', $username)->exists()) {
                    $n++;
                    $username = 'superadmin'.$n;
                }
                DB::table('tb_user')->insert([
                    'id_sekolah' => $sekolah->id_sekolah,
                    'id_role' => $saId,
                    'username' => $username,
                    'password' => Hash::make('password'),
                    'nama_lengkap' => 'Super Admin',
                    'is_active' => true,
                    'created_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        $devId = DB::table('roles')->where('nama_role', 'developer')->value('id_role');
        $saId = DB::table('roles')->where('nama_role', 'super admin')->value('id_role');

        // Kembalikan developer global menjadi super admin.
        if ($devId && $saId) {
            DB::table('tb_user')
                ->where('id_role', $devId)
                ->whereNull('id_sekolah')
                ->update(['id_role' => $saId]);
            DB::table('roles')->where('id_role', $devId)->delete();
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `roles` MODIFY `nama_role` ENUM('super admin','admin','kasir','') NULL");
        }
    }
};
