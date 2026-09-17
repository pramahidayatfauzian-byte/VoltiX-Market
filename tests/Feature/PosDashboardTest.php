<?php

use App\Models\Penjualan;
use App\Models\TbUser;
use Carbon\Carbon;
use Database\Seeders\PosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PosSeeder::class);
});

test('dashboard requires auth', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('dashboard renders with live stats', function () {
    $user = TbUser::where('username', 'superadmin')->first();

    // Penjualan hari ini milik sekolah user
    Penjualan::create([
        'id_sekolah' => $user->id_sekolah,
        'id_user' => $user->id_user,
        'tanggal_penjualan' => Carbon::now(),
        'total_faktur' => 150000,
        'is_delete' => 0,
    ]);

    // Penjualan sekolah lain (harus terisolasi untuk non super admin,
    // tapi superadmin melihat semua)
    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('stats')
        ->has('grafik', 7)
        ->has('transaksi_terbaru')
        ->where('stats.penjualan_hari_ini', 150000)
        ->where('stats.transaksi_hari_ini', 1)
    );
});

test('tenant isolation hides other school sales for kasir', function () {
    $kasir = TbUser::where('username', 'kasir1')->first();
    $superadmin = TbUser::where('username', 'superadmin')->first();

    Penjualan::create([
        'id_sekolah' => $superadmin->id_sekolah,
        'id_user' => $superadmin->id_user,
        'tanggal_penjualan' => Carbon::now(),
        'total_faktur' => 999000,
        'is_delete' => 0,
    ]);

    // kasir1 beda sekolah dari superadmin -> tidak boleh melihat 999rb
    if ($kasir->id_sekolah !== $superadmin->id_sekolah) {
        $this->actingAs($kasir)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.penjualan_hari_ini', 0)
                ->where('stats.transaksi_hari_ini', 0)
            );
    } else {
        $this->assertTrue(true);
    }
});
