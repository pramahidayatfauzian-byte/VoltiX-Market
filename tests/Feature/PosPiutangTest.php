<?php

use App\Models\Penjualan;
use App\Models\TbUser;
use Database\Seeders\PosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PosSeeder::class);
});

function buatKredit($user, $total, $bayar = 0): Penjualan
{
    return Penjualan::create([
        'id_sekolah' => $user->id_sekolah, 'id_user' => $user->id_user,
        'tanggal_penjualan' => now(),
        'total_faktur' => $total, 'total_bayar' => $bayar,
        'status_pembayaran' => 'belum bayar',
        'jenis_transaksi' => 'kredit', 'cara_bayar' => 'cash',
        'created_by' => $user->id_user, 'is_delete' => 0,
    ]);
}

test('piutang page requires auth and blocks kasir', function () {
    $this->get(route('piutang.index'))->assertRedirect(route('login'));

    $kasir = TbUser::where('username', 'kasir1')->first();
    $this->actingAs($kasir)->get(route('piutang.index'))->assertForbidden();
});

test('cicilan reduces balance and full payment closes it', function () {
    $admin = TbUser::where('username', 'admin1')->first();
    $p = buatKredit($admin, 100000, 20000);

    // ringkasan: sisa 80000
    $this->actingAs($admin)->getJson(route('piutang.data'))
        ->assertOk()
        ->assertJsonPath('ringkasan.faktur', 1)
        ->assertJsonPath('ringkasan.total_piutang', 80000);

    // cicil 30000 -> sisa 50000, masih belum bayar
    $this->actingAs($admin)->post(route('piutang.lunasi', $p->id_penjualan), [
        'jumlah' => 30000, 'cara_bayar' => 'transfer',
    ])->assertSessionHas('success');
    expect($p->fresh()->status_pembayaran)->toBe('belum bayar')
        ->and((float) $p->fresh()->total_bayar)->toBe(50000.0);

    // lunasi 50000 -> lunas
    $this->actingAs($admin)->post(route('piutang.lunasi', $p->id_penjualan), [
        'jumlah' => 50000, 'cara_bayar' => 'cash',
    ])->assertSessionHas('success');
    expect($p->fresh()->status_pembayaran)->toBe('sudah bayar');

    // tidak lagi muncul di daftar
    $this->actingAs($admin)->getJson(route('piutang.data'))
        ->assertOk()->assertJsonPath('ringkasan.faktur', 0);
});

test('cannot pay already settled invoice', function () {
    $admin = TbUser::where('username', 'admin1')->first();
    $p = buatKredit($admin, 50000, 0);
    $p->update(['status_pembayaran' => 'sudah bayar', 'total_bayar' => 50000]);

    $this->actingAs($admin)->post(route('piutang.lunasi', $p->id_penjualan), [
        'jumlah' => 10000, 'cara_bayar' => 'cash',
    ])->assertSessionHasErrors('piutang');
});

test('piutang respects tenant isolation', function () {
    $admin = TbUser::where('username', 'admin1')->first();
    $lain = TbUser::where('username', 'admin2')->first();
    $p = buatKredit($lain, 70000, 0);

    $this->actingAs($admin)->getJson(route('piutang.data'))
        ->assertOk()->assertJsonPath('ringkasan.faktur', 0);

    $this->actingAs($admin)->post(route('piutang.lunasi', $p->id_penjualan), [
        'jumlah' => 70000, 'cara_bayar' => 'cash',
    ])->assertSessionHasErrors('piutang');

    expect((float) $p->fresh()->total_bayar)->toBe(0.0)
        ->and($p->fresh()->status_pembayaran)->toBe('belum bayar');
});
