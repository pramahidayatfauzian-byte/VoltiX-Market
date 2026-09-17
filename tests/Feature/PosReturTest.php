<?php

use App\Models\Barang;
use App\Models\DetailPembelian;
use App\Models\DetailPenjualan;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\Retur;
use App\Models\Supplier;
use App\Models\TbUser;
use Database\Seeders\PosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PosSeeder::class);
});

function siapkanJual($admin): array
{
    $barang = Barang::create([
        'id_sekolah' => $admin->id_sekolah, 'nama' => 'Brg Retur',
        'harga_beli' => 4000, 'harga_jual' => 6000,
        'stok' => 10, 'is_active' => true, 'is_delete' => 0,
    ]);
    $jual = Penjualan::create([
        'id_sekolah' => $admin->id_sekolah, 'id_user' => $admin->id_user,
        'tanggal_penjualan' => now(), 'total_faktur' => 12000,
        'is_delete' => 0,
    ]);
    DetailPenjualan::create([
        'id_penjualan' => $jual->id_penjualan, 'id_barang' => $barang->id_barang,
        'jumlah_barang' => 2, 'harga_jual' => 6000, 'subtotal' => 12000,
    ]);

    return [$barang, $jual];
}

test('retur page requires auth and blocks kasir', function () {
    $this->get(route('retur.index'))->assertRedirect(route('login'));

    $kasir = TbUser::where('username', 'kasir1')->first();
    $this->actingAs($kasir)->get(route('retur.index'))->assertForbidden();
});

test('retur penjualan adds stock and caps at sold qty', function () {
    $admin = TbUser::where('username', 'admin1')->first();
    [$barang, $jual] = siapkanJual($admin);

    // retur 1 dari 2 -> stok 11
    $this->actingAs($admin)->post(route('retur.store-jual'), [
        'id_penjualan' => $jual->id_penjualan,
        'alasan' => 'rusak',
        'items' => [['id_barang' => $barang->id_barang, 'jumlah' => 1]],
    ])->assertSessionHas('success');

    expect($barang->fresh()->stok)->toBe(11);
    expect(Retur::count())->toBe(1);

    // retur 2 (melebihi sisa 1) -> ditolak, stok tetap
    $this->actingAs($admin)->post(route('retur.store-jual'), [
        'id_penjualan' => $jual->id_penjualan,
        'items' => [['id_barang' => $barang->id_barang, 'jumlah' => 2]],
    ])->assertSessionHasErrors('retur');

    expect($barang->fresh()->stok)->toBe(11);
});

test('retur pembelian reduces stock and needs enough stock', function () {
    $admin = TbUser::where('username', 'admin1')->first();
    $sup = Supplier::create([
        'id_sekolah' => $admin->id_sekolah, 'nama' => 'Sup',
        'created_by' => $admin->id_user, 'is_delete' => 0,
    ]);
    $barang = Barang::create([
        'id_sekolah' => $admin->id_sekolah, 'nama' => 'Brg Beli',
        'harga_beli' => 4000, 'harga_jual' => 6000,
        'stok' => 1, 'is_active' => true, 'is_delete' => 0,
    ]);
    $beli = Pembelian::create([
        'id_sekolah' => $admin->id_sekolah, 'id_supplier' => $sup->id_supplier,
        'id_user' => $admin->id_user, 'nomor_faktur' => 'PB-R1',
        'tanggal_faktur' => now(), 'total_bayar' => 8000,
        'status_pembelian' => 'selesai', 'jenis_transaksi' => 'tunai',
        'cara_bayar' => 'cash', 'created_by' => $admin->id_user, 'is_delete' => 0,
    ]);
    DetailPembelian::create([
        'id_pembelian' => $beli->id_pembelian, 'id_barang' => $barang->id_barang,
        'jumlah' => 2, 'harga_beli' => 4000, 'subtotal' => 8000,
    ]);

    // stok 1, retur 2 -> ditolak
    $this->actingAs($admin)->post(route('retur.store-beli'), [
        'id_pembelian' => $beli->id_pembelian,
        'items' => [['id_barang' => $barang->id_barang, 'jumlah' => 2]],
    ])->assertSessionHasErrors('retur');

    // retur 1 -> stok 0
    $this->actingAs($admin)->post(route('retur.store-beli'), [
        'id_pembelian' => $beli->id_pembelian,
        'items' => [['id_barang' => $barang->id_barang, 'jumlah' => 1]],
    ])->assertSessionHas('success');

    expect($barang->fresh()->stok)->toBe(0);
});

test('retur respects tenant isolation', function () {
    $admin = TbUser::where('username', 'admin1')->first();
    $lain = TbUser::where('username', 'admin2')->first();
    [, $jual] = siapkanJual($lain);

    $this->actingAs($admin)->post(route('retur.store-jual'), [
        'id_penjualan' => $jual->id_penjualan,
        'items' => [['id_barang' => 1, 'jumlah' => 1]],
    ])->assertSessionHasErrors('retur');
});
