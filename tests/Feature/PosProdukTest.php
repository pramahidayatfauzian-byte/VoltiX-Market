<?php

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\KelompokKategori;
use App\Models\TbUser;
use Database\Seeders\PosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PosSeeder::class);
});

test('produk page requires auth', function () {
    $this->get(route('produk.index'))->assertRedirect(route('login'));
});

test('produk crud works with tenant scope', function () {
    $admin = TbUser::where('username', 'admin1')->first();

    // create
    $this->actingAs($admin)->post(route('produk.store'), [
        'barcode' => 'T001',
        'nama' => 'Produk Uji',
        'satuan' => 'pcs',
        'harga_jual' => 10000,
        'stok' => 5,
    ])->assertSessionHas('success');

    $b = Barang::where('barcode', 'T001')->first();
    expect($b)->not->toBeNull()
        ->and((int) $b->id_sekolah)->toBe((int) $admin->id_sekolah);

    // list json contains it
    $this->actingAs($admin)->getJson(route('produk.data', ['search' => 'Produk Uji']))
        ->assertOk()->assertJsonPath('data.0.nama', 'Produk Uji');

    // update
    $this->actingAs($admin)->put(route('produk.update', $b->id_barang), [
        'nama' => 'Produk Uji Update',
        'harga_jual' => 12000,
        'stok' => 7,
    ])->assertSessionHas('success');

    // toggle nonaktif
    $this->actingAs($admin)->patch(route('produk.toggle', $b->id_barang))
        ->assertSessionHas('success');
    expect($b->fresh()->is_active)->toBeFalse();

    // delete (belum dipakai transaksi)
    $this->actingAs($admin)->delete(route('produk.destroy', $b->id_barang))
        ->assertSessionHas('success');
    expect($b->fresh()->is_delete)->toBe(1);
});

test('kategori cannot be deleted while used by product', function () {
    $admin = TbUser::where('username', 'admin1')->first();
    $kel = KelompokKategori::create([
        'id_sekolah' => $admin->id_sekolah, 'nama_kelompok' => 'Kel Uji',
        'created_by' => $admin->id_user,
    ]);
    $kat = Kategori::create([
        'id_kelompok' => $kel->id, 'nama' => 'Kat Uji',
        'created_by' => $admin->id_user, 'is_delete' => 0,
    ]);
    Barang::create([
        'id_sekolah' => $admin->id_sekolah, 'nama' => 'Brg Kat',
        'id_kategori' => $kat->id_kategori, 'harga_jual' => 5000,
        'stok' => 3, 'is_active' => true, 'is_delete' => 0,
    ]);

    $this->actingAs($admin)->delete(route('kategori.destroy', $kat->id_kategori))
        ->assertSessionHasErrors('kategori');

    // kelompok juga tidak bisa dihapus karena dipakai kategori
    $this->actingAs($admin)->delete(route('kelompok.destroy', $kel->id))
        ->assertSessionHasErrors('kelompok');

    expect($kat->fresh()->is_delete)->not->toBe(1);
});

test('kelompok crud works', function () {
    $admin = TbUser::where('username', 'admin1')->first();

    $this->actingAs($admin)->post(route('kelompok.store'), [
        'nama_kelompok' => 'Kel Baru',
    ])->assertSessionHas('success');

    $k = KelompokKategori::where('nama_kelompok', 'Kel Baru')->first();
    expect($k)->not->toBeNull()
        ->and((int) $k->id_sekolah)->toBe((int) $admin->id_sekolah);

    $this->actingAs($admin)->put(route('kelompok.update', $k->id), [
        'nama_kelompok' => 'Kel Baru Update',
    ])->assertSessionHas('success');

    $this->actingAs($admin)->delete(route('kelompok.destroy', $k->id))
        ->assertSessionHas('success');
});
