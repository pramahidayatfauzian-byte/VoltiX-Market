<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Sekolah;
use App\Models\TbUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PosSeeder extends Seeder
{
    public function run(): void
    {
        // Roles (developer = akses global penuh, seperti super admin lama)
        foreach (['super admin', 'admin', 'kasir', 'developer'] as $nama) {
            Role::firstOrCreate(['nama_role' => $nama]);
        }

        $superAdminRole = Role::where('nama_role', 'super admin')->first();
        $adminRole = Role::where('nama_role', 'admin')->first();
        $kasirRole = Role::where('nama_role', 'kasir')->first();
        $developerRole = Role::where('nama_role', 'developer')->first();

        // Sekolah / Tenant
        $sekolahData = [
            ['kode_sekolah' => 'SMKN2TSM', 'nama_sekolah' => 'SMKN 2 Tasikmalaya', 'alamat_sekolah' => 'Jl. Noenoeng Tisnasaputra, Tasikmalaya', 'website' => null, 'is_active' => true],
            ['kode_sekolah' => 'SMKN1TSM', 'nama_sekolah' => 'SMKN 1 Tasikmalaya', 'alamat_sekolah' => 'Jl. Depok, Tasikmalaya', 'website' => null, 'is_active' => true],
            ['kode_sekolah' => 'SMKN3TSM', 'nama_sekolah' => 'SMKN 3 Tasikmalaya', 'alamat_sekolah' => 'Jl. Tasikmalaya', 'website' => null, 'is_active' => true],
            ['kode_sekolah' => 'SMKN4TSM', 'nama_sekolah' => 'SMKN 4 Tasikmalaya', 'alamat_sekolah' => 'Jl. Tasikmalaya', 'website' => null, 'is_active' => true],
        ];

        foreach ($sekolahData as $row) {
            Sekolah::firstOrCreate(['kode_sekolah' => $row['kode_sekolah']], $row);
        }

        $semuaSekolah = Sekolah::all();

        // Developer — global, bisa akses semua sekolah (id_sekolah NULL).
        // (Menggantikan peran super admin lama.)
        TbUser::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'id_sekolah' => null,
                'id_role' => $developerRole->id_role,
                'nama_lengkap' => 'Developer',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        // Super Admin + Admin + Kasir per sekolah
        foreach ($semuaSekolah as $index => $sekolah) {
            $n = $index + 1;
            TbUser::updateOrCreate(
                ['username' => "superadmin{$n}"],
                [
                    'id_sekolah' => $sekolah->id_sekolah,
                    'id_role' => $superAdminRole->id_role,
                    'nama_lengkap' => "Super Admin {$sekolah->nama_sekolah}",
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ]
            );

            TbUser::updateOrCreate(
                ['username' => "admin{$n}"],
                [
                    'id_sekolah' => $sekolah->id_sekolah,
                    'id_role' => $adminRole->id_role,
                    'nama_lengkap' => "Admin {$sekolah->nama_sekolah}",
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ]
            );

            TbUser::updateOrCreate(
                ['username' => "kasir{$n}"],
                [
                    'id_sekolah' => $sekolah->id_sekolah,
                    'id_role' => $kasirRole->id_role,
                    'nama_lengkap' => "Kasir {$sekolah->nama_sekolah}",
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ]
            );
        }
    }
}
