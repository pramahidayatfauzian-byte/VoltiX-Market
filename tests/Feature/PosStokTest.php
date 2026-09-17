<?php

use App\Models\Barang;
use App\Models\DetailPembelian;
use App\Models\DetailPenjualan;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\Supplier;
use App\Models\TbUser;
use Carbon\Carbon;
use Database\Seeders\PosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PosSeeder::class);
});

test('dashboard warns low stock products', function () {
    $admin = TbUser::where('username', 'admin1')->first();

    Barang::create([
        'id_sekolah' => $admin->id_sekolah, 'nama' => 'Hampir Habis',
        'harga_jual' => 1000, 'stok' => 2, 'is_active' => true, 'is_delete' => 0,
    ]);
    Barang::create([
        'id_sekolah' => $admin->id_sekolah, 'nama' => 'Masih Banyak',
        'harga_jual' => 1000, 'stok' => 50, 'is_active' => true, 'is_delete' => 0,
    ]);

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('stok_menipis', 1)
            ->where('stok_menipis.0.nama', 'Hampir Habis')
            ->where('batas_menipis', 5)
        );
});

test('kartu stok computes running balance from real movements', function () {
    $admin = TbUser::where('username', 'admin1')->first();
    $sup = Supplier::create([
        'id_sekolah' => $admin->id_sekolah, 'nama' => 'Sup',
        'created_by' => $admin->id_user, 'is_delete' => 0,
    ]);
    $barang = Barang::create([
        'id_sekolah' => $admin->id_sekolah, 'nama' => 'Brg Kartu',
        'harga_beli' => 4000, 'harga_jual' => 6000,
        'stok' => 10, 'is_active' => true, 'is_delete' => 0,
    ]);

    // masuk 5 via pembelian selesai
    $beli = Pembelian::create([
        'id_sekolah' => $admin->id_sekolah, 'id_supplier' => $sup->id_supplier,
        'id_user' => $admin->id_user, 'nomor_faktur' => 'PB-K1',
        'tanggal_faktur' => Carbon::today()->subDays(2),
        'total_bayar' => 20000, 'status_pembelian' => 'selesai',
        'jenis_transaksi' => 'tunai', 'cara_bayar' => 'cash',
        'created_by' => $admin->id_user, 'is_delete' => 0,
    ]);
    DetailPembelian::create([
        'id_pembelian' => $beli->id_pembelian, 'id_barang' => $barang->id_barang,
        'jumlah' => 5, 'harga_beli' => 4000, 'subtotal' => 20000,
    ]);

    // keluar 3 via penjualan
    $jual = Penjualan::create([
        'id_sekolah' => $admin->id_sekolah, 'id_user' => $admin->id_user,
        'tanggal_penjualan' => Carbon::today()->subDay(),
        'total_faktur' => 18000, 'is_delete' => 0,
    ]);
    DetailPenjualan::create([
        'id_penjualan' => $jual->id_penjualan, 'id_barang' => $barang->id_barang,
        'jumlah_barang' => 3, 'harga_jual' => 6000, 'subtotal' => 18000,
    ]);
    $barang->update(['stok' => 12]); // 10 + 5 - 3

    // draft tidak masuk hitungan
    $draft = Pembelian::create([
        'id_sekolah' => $admin->id_sekolah, 'id_supplier' => $sup->id_supplier,
        'id_user' => $admin->id_user, 'nomor_faktur' => 'PB-DRAFT',
        'tanggal_faktur' => now(),
        'total_bayar' => 4000, 'status_pembelian' => 'draft',
        'jenis_transaksi' => 'tunai', 'cara_bayar' => 'cash',
        'created_by' => $admin->id_user, 'is_delete' => 0,
    ]);
    DetailPembelian::create([
        'id_pembelian' => $draft->id_pembelian, 'id_barang' => $barang->id_barang,
        'jumlah' => 99, 'harga_beli' => 4000, 'subtotal' => 396000,
    ]);

    $res = $this->actingAs($admin)->getJson(route('produk.riwayat', $barang->id_barang));
    $res->assertOk()
        ->assertJsonPath('total_masuk', 5)
        ->assertJsonPath('total_keluar', 3)
        ->assertJsonPath('barang.stok', 12)
        ->assertJsonCount(2, 'kartu')
        ->assertJsonPath('kartu.0.masuk', 5)
        ->assertJsonPath('kartu.0.sisa', 15)
        ->assertJsonPath('kartu.1.keluar', 3)
        ->assertJsonPath('kartu.1.sisa', 12);
});

test('riwayat respects tenant isolation', function () {
    $admin = TbUser::where('username', 'admin1')->first();
    $lain = TbUser::where('username', 'admin2')->first();

    $barang = Barang::create([
        'id_sekolah' => $lain->id_sekolah, 'nama' => 'Milik Lain',
        'harga_jual' => 1000, 'stok' => 5, 'is_active' => true, 'is_delete' => 0,
    ]);

    $this->actingAs($admin)->getJson(route('produk.riwayat', $barang->id_barang))
        ->assertNotFound();
});
