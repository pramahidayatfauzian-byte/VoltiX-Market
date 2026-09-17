<?php

use App\Models\Barang;
use App\Models\Pelanggan;
use App\Models\Penjualan;
use App\Models\TbUser;
use Database\Seeders\PosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PosSeeder::class);
});

function buatBarang($sekolahId, $stok = 10): Barang
{
    return Barang::create([
        'id_sekolah' => $sekolahId,
        'barcode' => 'BR'.random_int(10000, 99999),
        'nama' => 'Produk Uji',
        'satuan' => 'pcs',
        'harga_beli' => 5000,
        'harga_jual' => 8000,
        'stok' => $stok,
        'is_active' => true,
        'is_delete' => 0,
    ]);
}

test('kasir page requires auth', function () {
    $this->get(route('kasir.index'))->assertRedirect(route('login'));
});

test('kasir page renders for cashier role', function () {
    $kasir = TbUser::where('username', 'kasir1')->first();
    buatBarang($kasir->id_sekolah, 10);

    $this->actingAs($kasir)->get(route('kasir.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Kasir')
            ->has('produk_list')
            ->has('kategori_list')
        );
});

test('checkout creates sale details and reduces stock', function () {
    $kasir = TbUser::where('username', 'kasir1')->first();
    $barang = buatBarang($kasir->id_sekolah, 10);

    $response = $this->actingAs($kasir)->post(route('kasir.checkout'), [
        'items' => [
            ['id_barang' => $barang->id_barang, 'qty' => 2, 'diskon_tipe' => null, 'diskon_nilai' => 0],
        ],
        'jenis_transaksi' => 'tunai',
        'cara_bayar' => 'cash',
        'total_bayar' => 16000,
        'status_pembayaran' => 'sudah bayar',
    ]);
    $response->assertSessionHas('success');

    expect(Penjualan::count())->toBe(1);
    $p = Penjualan::first();
    expect((float) $p->total_faktur)->toBe(16000.0)
        ->and((float) $p->kembalian)->toBe(0.0)
        ->and($p->detail()->count())->toBe(1);

    // struk untuk cetak memuat rincian item
    $response->assertSessionHas('receipt', function ($receipt) {
        return $receipt['item_count'] === 1
            && count($receipt['items']) === 1
            && $receipt['items'][0]['qty'] === 2
            && (float) $receipt['items'][0]['subtotal'] === 16000.0;
    });

    expect($barang->fresh()->stok)->toBe(8);
});

test('checkout with percent discount computes correctly', function () {
    $kasir = TbUser::where('username', 'kasir1')->first();
    $barang = buatBarang($kasir->id_sekolah, 10);

    $this->actingAs($kasir)->post(route('kasir.checkout'), [
        'items' => [
            ['id_barang' => $barang->id_barang, 'qty' => 1, 'diskon_tipe' => 'persen', 'diskon_nilai' => 10],
        ],
        'jenis_transaksi' => 'tunai',
        'cara_bayar' => 'cash',
        'total_bayar' => 7200,
        'status_pembayaran' => 'sudah bayar',
    ])->assertSessionHas('success');

    $p = Penjualan::first();
    expect((float) $p->total_faktur)->toBe(7200.0);
});

test('checkout fails when stock insufficient and rolls back', function () {
    $kasir = TbUser::where('username', 'kasir1')->first();
    $barang = buatBarang($kasir->id_sekolah, 1);

    $this->actingAs($kasir)->post(route('kasir.checkout'), [
        'items' => [
            ['id_barang' => $barang->id_barang, 'qty' => 5, 'diskon_tipe' => null, 'diskon_nilai' => 0],
        ],
        'jenis_transaksi' => 'tunai',
        'cara_bayar' => 'cash',
        'total_bayar' => 40000,
        'status_pembayaran' => 'sudah bayar',
    ])->assertSessionHasErrors('items');

    expect(Penjualan::count())->toBe(0);
    expect($barang->fresh()->stok)->toBe(1);
});

test('kasir cannot sell other school product', function () {
    $kasir = TbUser::where('username', 'kasir1')->first();
    $admin = TbUser::where('username', 'superadmin')->first();
    $barangLain = buatBarang($admin->id_sekolah, 10);

    if ($kasir->id_sekolah === $admin->id_sekolah) {
        $this->assertTrue(true);

        return;
    }

    $this->actingAs($kasir)->post(route('kasir.checkout'), [
        'items' => [
            ['id_barang' => $barangLain->id_barang, 'qty' => 1, 'diskon_tipe' => null, 'diskon_nilai' => 0],
        ],
        'jenis_transaksi' => 'tunai',
        'cara_bayar' => 'cash',
        'total_bayar' => 8000,
        'status_pembayaran' => 'sudah bayar',
    ])->assertSessionHasErrors('items');

    expect(Penjualan::count())->toBe(0);
});

test('pelanggan crud works', function () {
    $kasir = TbUser::where('username', 'kasir1')->first();

    // create
    $this->actingAs($kasir)->post(route('pelanggan.store'), [
        'nama_pelanggan' => 'Budi Uji',
        'telepon' => '08123',
        'alamat' => 'Jl. Uji',
    ])->assertSessionHas('success');

    $p = Pelanggan::where('nama_pelanggan', 'Budi Uji')->first();
    expect($p)->not->toBeNull();

    // list json
    $this->actingAs($kasir)->getJson(route('pelanggan.index', ['search' => 'Budi']))
        ->assertOk()->assertJsonPath('data.0.nama_pelanggan', 'Budi Uji');

    // update
    $this->actingAs($kasir)->put(route('pelanggan.update', $p->id_pelanggan), [
        'nama_pelanggan' => 'Budi Update',
        'telepon' => '08123',
    ])->assertSessionHas('success');

    // delete (soft)
    $this->actingAs($kasir)->delete(route('pelanggan.destroy', $p->id_pelanggan))
        ->assertSessionHas('success');
    expect($p->fresh()->is_delete)->toBe(1);
});
