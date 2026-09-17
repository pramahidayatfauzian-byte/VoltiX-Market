<?php

use App\Models\Barang;
use App\Models\Pembelian;
use App\Models\Supplier;
use App\Models\TbUser;
use Database\Seeders\PosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PosSeeder::class);
});

function buatSupplier($sekolahId): Supplier
{
    return Supplier::create([
        'id_sekolah' => $sekolahId, 'nama' => 'Sup Uji',
        'no_telepon' => '081', 'created_by' => 1, 'is_delete' => 0,
    ]);
}

function buatBarangBeli($sekolahId, $stok = 5): Barang
{
    return Barang::create([
        'id_sekolah' => $sekolahId, 'barcode' => 'PB'.random_int(10000, 99999),
        'nama' => 'Brg Beli', 'satuan' => 'pcs',
        'harga_beli' => 4000, 'harga_jual' => 6000,
        'stok' => $stok, 'is_active' => true, 'is_delete' => 0,
    ]);
}

test('pembelian page requires auth and blocks kasir', function () {
    $this->get(route('pembelian.index'))->assertRedirect(route('login'));

    $kasir = TbUser::where('username', 'kasir1')->first();
    $this->actingAs($kasir)->get(route('pembelian.index'))->assertForbidden();
});

test('draft purchase does not change stock, selesaikan adds stock', function () {
    $admin = TbUser::where('username', 'admin1')->first();
    $sup = buatSupplier($admin->id_sekolah);
    $barang = buatBarangBeli($admin->id_sekolah, 5);

    // draft
    $this->actingAs($admin)->post(route('pembelian.store'), [
        'id_supplier' => $sup->id_supplier,
        'nomor_faktur' => 'PB-DRAFT-1',
        'tanggal_faktur' => now()->format('Y-m-d H:i:s'),
        'status_pembelian' => 'draft',
        'jenis_transaksi' => 'tunai',
        'cara_bayar' => 'cash',
        'items' => [
            ['id_barang' => $barang->id_barang, 'jumlah' => 3, 'harga_beli' => 4000],
        ],
    ])->assertSessionHas('success');

    expect($barang->fresh()->stok)->toBe(5);
    $p = Pembelian::where('nomor_faktur', 'PB-DRAFT-1')->first();
    expect((float) $p->total_bayar)->toBe(12000.0);

    // selesaikan -> stok 5 + 3 = 8
    $this->actingAs($admin)->post(route('pembelian.selesaikan', $p->id_pembelian))
        ->assertSessionHas('success');
    expect($barang->fresh()->stok)->toBe(8);
    expect($p->fresh()->status_pembelian)->toBe('selesai');
});

test('selesai purchase adds stock immediately', function () {
    $admin = TbUser::where('username', 'admin1')->first();
    $sup = buatSupplier($admin->id_sekolah);
    $barang = buatBarangBeli($admin->id_sekolah, 2);

    $this->actingAs($admin)->post(route('pembelian.store'), [
        'id_supplier' => $sup->id_supplier,
        'nomor_faktur' => 'PB-OK-1',
        'tanggal_faktur' => now()->format('Y-m-d H:i:s'),
        'status_pembelian' => 'selesai',
        'jenis_transaksi' => 'kredit',
        'cara_bayar' => 'transfer',
        'items' => [
            ['id_barang' => $barang->id_barang, 'jumlah' => 4, 'harga_beli' => 4500],
        ],
    ])->assertSessionHas('success');

    expect($barang->fresh()->stok)->toBe(6);
});

test('selesai purchase cannot be deleted, draft can', function () {
    $admin = TbUser::where('username', 'admin1')->first();
    $sup = buatSupplier($admin->id_sekolah);
    $barang = buatBarangBeli($admin->id_sekolah, 5);

    $this->actingAs($admin)->post(route('pembelian.store'), [
        'id_supplier' => $sup->id_supplier,
        'nomor_faktur' => 'PB-DEL-1',
        'tanggal_faktur' => now()->format('Y-m-d H:i:s'),
        'status_pembelian' => 'selesai',
        'jenis_transaksi' => 'tunai',
        'cara_bayar' => 'cash',
        'items' => [
            ['id_barang' => $barang->id_barang, 'jumlah' => 1, 'harga_beli' => 4000],
        ],
    ])->assertSessionHas('success');

    $selesai = Pembelian::where('nomor_faktur', 'PB-DEL-1')->first();
    $this->actingAs($admin)->delete(route('pembelian.destroy', $selesai->id_pembelian))
        ->assertSessionHasErrors('faktur');

    $this->actingAs($admin)->post(route('pembelian.store'), [
        'id_supplier' => $sup->id_supplier,
        'nomor_faktur' => 'PB-DEL-2',
        'tanggal_faktur' => now()->format('Y-m-d H:i:s'),
        'status_pembelian' => 'draft',
        'jenis_transaksi' => 'tunai',
        'cara_bayar' => 'cash',
        'items' => [
            ['id_barang' => $barang->id_barang, 'jumlah' => 1, 'harga_beli' => 4000],
        ],
    ])->assertSessionHas('success');

    $draft = Pembelian::where('nomor_faktur', 'PB-DEL-2')->first();
    $this->actingAs($admin)->delete(route('pembelian.destroy', $draft->id_pembelian))
        ->assertSessionHas('success');
});

test('supplier crud and protection when used', function () {
    $admin = TbUser::where('username', 'admin1')->first();

    $this->actingAs($admin)->post(route('supplier.store'), [
        'nama' => 'Sup Baru', 'no_telepon' => '082',
    ])->assertSessionHas('success');

    $s = Supplier::where('nama', 'Sup Baru')->first();
    expect((int) $s->id_sekolah)->toBe((int) $admin->id_sekolah);

    $this->actingAs($admin)->getJson(route('supplier.data', ['search' => 'Sup Baru']))
        ->assertOk()->assertJsonPath('data.0.nama', 'Sup Baru');

    $this->actingAs($admin)->put(route('supplier.update', $s->id_supplier), [
        'nama' => 'Sup Baru Update',
    ])->assertSessionHas('success');

    // pakai supplier di pembelian -> hapus ditolak
    $barang = buatBarangBeli($admin->id_sekolah, 5);
    $this->actingAs($admin)->post(route('pembelian.store'), [
        'id_supplier' => $s->id_supplier,
        'nomor_faktur' => 'PB-SUP-1',
        'tanggal_faktur' => now()->format('Y-m-d H:i:s'),
        'status_pembelian' => 'draft',
        'jenis_transaksi' => 'tunai',
        'cara_bayar' => 'cash',
        'items' => [
            ['id_barang' => $barang->id_barang, 'jumlah' => 1, 'harga_beli' => 4000],
        ],
    ])->assertSessionHas('success');

    $this->actingAs($admin)->delete(route('supplier.destroy', $s->id_supplier))
        ->assertSessionHasErrors('supplier');
});
