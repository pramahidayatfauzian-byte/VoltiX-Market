<?php

use App\Models\Penjualan;
use App\Models\TbUser;
use Carbon\Carbon;
use Database\Seeders\PosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PosSeeder::class);
});

test('laporan requires auth and blocks kasir', function () {
    $this->get(route('laporan.index'))->assertRedirect(route('login'));
    $this->get(route('pengaturan.index'))->assertRedirect(route('login'));

    $kasir = TbUser::where('username', 'kasir1')->first();
    $this->actingAs($kasir)->get(route('laporan.index'))->assertForbidden();
    $this->actingAs($kasir)->get(route('pengaturan.index'))->assertForbidden();
});

test('laporan summary respects date filter and tenant', function () {
    $admin = TbUser::where('username', 'admin1')->first();

    Penjualan::create([
        'id_sekolah' => $admin->id_sekolah, 'id_user' => $admin->id_user,
        'tanggal_penjualan' => Carbon::today()->subDays(2),
        'total_faktur' => 100000, 'is_delete' => 0,
    ]);
    Penjualan::create([
        'id_sekolah' => $admin->id_sekolah, 'id_user' => $admin->id_user,
        'tanggal_penjualan' => Carbon::today()->subDays(40),
        'total_faktur' => 999000, 'is_delete' => 0,
    ]);

    // tanpa filter tanggal -> keduanya masuk
    $res = $this->actingAs($admin)->getJson(route('laporan.data'));
    $res->assertOk()->assertJsonPath('ringkasan.transaksi', 2);

    // filter 7 hari terakhir -> hanya 1
    $res = $this->actingAs($admin)->getJson(route('laporan.data', [
        'tgl_mulai' => Carbon::today()->subDays(7)->format('Y-m-d'),
        'tgl_akhir' => Carbon::today()->format('Y-m-d'),
    ]));
    $res->assertOk()
        ->assertJsonPath('ringkasan.transaksi', 1)
        ->assertJsonPath('ringkasan.omzet', 100000);
});

test('laporan export returns csv', function () {
    $admin = TbUser::where('username', 'admin1')->first();

    Penjualan::create([
        'id_sekolah' => $admin->id_sekolah, 'id_user' => $admin->id_user,
        'tanggal_penjualan' => now(), 'total_faktur' => 50000, 'is_delete' => 0,
    ]);

    $res = $this->actingAs($admin)->get(route('laporan.export'));
    $res->assertOk();
    expect($res->headers->get('Content-Type'))->toContain('text/csv');
});

test('pengaturan updates own school and password', function () {
    $admin = TbUser::where('username', 'admin1')->first();

    // update sekolah sendiri
    $this->actingAs($admin)->put(route('pengaturan.sekolah', $admin->id_sekolah), [
        'nama_sekolah' => 'SMKN 1 Baru',
        'alamat_sekolah' => 'Jl. Baru',
    ])->assertSessionHas('success');
    expect(\App\Models\Sekolah::find($admin->id_sekolah)->nama_sekolah)->toBe('SMKN 1 Baru');

    // admin tidak boleh edit sekolah lain
    $lain = TbUser::where('username', 'admin2')->first()->id_sekolah;
    $this->actingAs($admin)->put(route('pengaturan.sekolah', $lain), [
        'nama_sekolah' => 'Ilegal',
    ])->assertForbidden();

    // ubah password dengan current benar
    $this->actingAs($admin)->post(route('pengaturan.password'), [
        'current_password' => 'password',
        'password' => 'baru1234',
        'password_confirmation' => 'baru1234',
    ])->assertSessionHas('success');
    expect(Hash::check('baru1234', $admin->fresh()->getRawOriginal('password')))->toBeTrue();

    // current salah ditolak
    $this->actingAs($admin)->post(route('pengaturan.password'), [
        'current_password' => 'salah',
        'password' => 'x123456',
        'password_confirmation' => 'x123456',
    ])->assertSessionHasErrors('current_password');
});
