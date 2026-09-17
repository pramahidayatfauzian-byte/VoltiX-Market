<?php

use App\Models\Barang;
use App\Models\TbUser;
use Database\Seeders\PosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PosSeeder::class);
});

test('audit page requires auth and blocks kasir', function () {
    $this->get(route('audit.index'))->assertRedirect(route('login'));

    $kasir = TbUser::where('username', 'kasir1')->first();
    $this->actingAs($kasir)->get(route('audit.index'))->assertForbidden();
});

test('audit records create update delete with actor', function () {
    $admin = TbUser::where('username', 'admin1')->first();

    // tambah via controller (created_by terisi)
    $this->actingAs($admin)->post(route('produk.store'), [
        'nama' => 'Brg Audit',
        'harga_jual' => 9000,
        'stok' => 4,
    ])->assertSessionHas('success');

    $b = Barang::where('nama', 'Brg Audit')->first();

    $res = $this->actingAs($admin)->getJson(route('audit.data', ['modul' => 'Produk']));
    $res->assertOk();
    $isi = $res->json('data');
    $tambah = collect($isi)->firstWhere('aksi', 'tambah');
    expect($tambah)->not->toBeNull()
        ->and($tambah['aktor'])->toBe($admin->nama_lengkap);

    // edit
    $this->actingAs($admin)->put(route('produk.update', $b->id_barang), [
        'nama' => 'Brg Audit',
        'harga_jual' => 9500,
        'stok' => 4,
    ])->assertSessionHas('success');

    $res = $this->actingAs($admin)->getJson(route('audit.data', ['aksi' => 'edit']));
    expect(collect($res->json('data'))->firstWhere('modul', 'Produk'))->not->toBeNull();

    // hapus
    $this->actingAs($admin)->delete(route('produk.destroy', $b->id_barang))
        ->assertSessionHas('success');

    $res = $this->actingAs($admin)->getJson(route('audit.data', ['aksi' => 'hapus']));
    $hapus = collect($res->json('data'))->firstWhere('modul', 'Produk');
    expect($hapus)->not->toBeNull();
});

test('audit respects tenant isolation', function () {
    $admin1 = TbUser::where('username', 'admin1')->first();
    $admin2 = TbUser::where('username', 'admin2')->first();

    $this->actingAs($admin2)->post(route('produk.store'), [
        'nama' => 'Brg Sekolah Lain',
        'harga_jual' => 1000,
        'stok' => 1,
    ])->assertSessionHas('success');

    $res = $this->actingAs($admin1)->getJson(route('audit.data', ['search' => 'Brg Sekolah Lain']));
    expect($res->json('total'))->toBe(0);
});
