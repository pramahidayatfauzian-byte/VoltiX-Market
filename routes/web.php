<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KasirController;
use App\Http\Controllers\KategoriController;
use App\Http\Controllers\KelompokKategoriController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\PelangganController;
use App\Http\Controllers\PembelianController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\SekolahAktifController;
use App\Http\Controllers\PiutangController;
use App\Http\Controllers\ReturController;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Kasir / Transaksi
    Route::get('kasir', [KasirController::class, 'index'])->name('kasir.index');
    Route::get('kasir/produk', [KasirController::class, 'searchProduk'])->name('kasir.produk');
    Route::post('kasir/produk-cepat', [KasirController::class, 'storeCepat'])->name('kasir.produk-cepat');
    Route::get('kasir/pelanggan-cari', [KasirController::class, 'searchPelanggan'])->name('kasir.pelanggan-cari');
    Route::post('kasir/checkout', [KasirController::class, 'checkout'])->name('kasir.checkout');

    // Pelanggan (halaman standalone)
    Route::get('pelanggan', [PelangganController::class, 'page'])->name('pelanggan.page');
    Route::get('pelanggan/data', [PelangganController::class, 'index'])->name('pelanggan.data');
    Route::post('pelanggan', [PelangganController::class, 'store'])->name('pelanggan.store');
    Route::put('pelanggan/{id}', [PelangganController::class, 'update'])->name('pelanggan.update');
    Route::delete('pelanggan/{id}', [PelangganController::class, 'destroy'])->name('pelanggan.destroy');
    // Legacy tab kasir (kompatibilitas)
    Route::get('kasir/pelanggan', [PelangganController::class, 'index']);
    Route::post('kasir/pelanggan', [PelangganController::class, 'store']);
    Route::put('kasir/pelanggan/{id}', [PelangganController::class, 'update']);
    Route::delete('kasir/pelanggan/{id}', [PelangganController::class, 'destroy']);

    // Produk, Kategori, Kelompok Kategori
    Route::get('produk', [ProdukController::class, 'index'])->name('produk.index');
    Route::get('produk/data', [ProdukController::class, 'data'])->name('produk.data');
    Route::get('produk/lookup-barcode', [ProdukController::class, 'lookupBarcode'])->name('produk.lookup');
    Route::get('produk/{id}/riwayat', [ProdukController::class, 'riwayat'])->name('produk.riwayat');
    Route::get('produk/{id}', [ProdukController::class, 'show'])->name('produk.show');
    Route::post('produk', [ProdukController::class, 'store'])->name('produk.store');
    Route::put('produk/{id}', [ProdukController::class, 'update'])->name('produk.update');
    Route::delete('produk/{id}', [ProdukController::class, 'destroy'])->name('produk.destroy');
    Route::patch('produk/{id}/toggle', [ProdukController::class, 'toggle'])->name('produk.toggle');

    Route::get('kategori/data', [KategoriController::class, 'data'])->name('kategori.data');
    Route::post('kategori', [KategoriController::class, 'store'])->name('kategori.store');
    Route::put('kategori/{id}', [KategoriController::class, 'update'])->name('kategori.update');
    Route::delete('kategori/{id}', [KategoriController::class, 'destroy'])->name('kategori.destroy');

    Route::get('kelompok-kategori/data', [KelompokKategoriController::class, 'data'])->name('kelompok.data');
    Route::post('kelompok-kategori', [KelompokKategoriController::class, 'store'])->name('kelompok.store');
    Route::put('kelompok-kategori/{id}', [KelompokKategoriController::class, 'update'])->name('kelompok.update');
    Route::delete('kelompok-kategori/{id}', [KelompokKategoriController::class, 'destroy'])->name('kelompok.destroy');

    // Manajemen User
    Route::get('user', [UserController::class, 'index'])->name('user.index');
    Route::get('user/data', [UserController::class, 'data'])->name('user.data');
    Route::post('user', [UserController::class, 'store'])->name('user.store');
    Route::put('user/{id}', [UserController::class, 'update'])->name('user.update');
    Route::delete('user/{id}', [UserController::class, 'destroy'])->name('user.destroy');
    Route::patch('user/{id}/toggle', [UserController::class, 'toggle'])->name('user.toggle');
    Route::post('user/{id}/reset-password', [UserController::class, 'resetPassword'])->name('user.reset');

    // Pembelian & Supplier
    Route::get('pembelian', [PembelianController::class, 'index'])->name('pembelian.index');
    Route::get('pembelian/data', [PembelianController::class, 'data'])->name('pembelian.data');
    Route::get('pembelian/{id}', [PembelianController::class, 'show'])->name('pembelian.show');
    Route::post('pembelian', [PembelianController::class, 'store'])->name('pembelian.store');
    Route::post('pembelian/{id}/selesaikan', [PembelianController::class, 'selesaikan'])->name('pembelian.selesaikan');
    Route::delete('pembelian/{id}', [PembelianController::class, 'destroy'])->name('pembelian.destroy');

    Route::get('supplier', [SupplierController::class, 'index'])->name('supplier.index');
    Route::get('supplier/data', [SupplierController::class, 'data'])->name('supplier.data');
    Route::post('supplier', [SupplierController::class, 'store'])->name('supplier.store');
    Route::put('supplier/{id}', [SupplierController::class, 'update'])->name('supplier.update');
    Route::delete('supplier/{id}', [SupplierController::class, 'destroy'])->name('supplier.destroy');

    // Laporan
    Route::get('laporan', [LaporanController::class, 'index'])->name('laporan.index');
    Route::get('laporan/data', [LaporanController::class, 'data'])->name('laporan.data');
    Route::get('laporan/export', [LaporanController::class, 'export'])->name('laporan.export');

    // Notifikasi bell
    Route::get('notifikasi', [\App\Http\Controllers\NotifikasiController::class, 'index'])->name('notifikasi.index');

    // Piutang (kredit belum bayar)
    Route::get('piutang', [PiutangController::class, 'index'])->name('piutang.index');
    Route::get('piutang/data', [PiutangController::class, 'data'])->name('piutang.data');
    Route::post('piutang/{id}/lunasi', [PiutangController::class, 'lunasi'])->name('piutang.lunasi');

    // Retur penjualan & pembelian
    Route::get('retur', [ReturController::class, 'index'])->name('retur.index');
    Route::get('retur/data', [ReturController::class, 'data'])->name('retur.data');
    Route::get('retur/penjualan-cari', [ReturController::class, 'cariPenjualan'])->name('retur.cari-jual');
    Route::get('retur/pembelian-cari', [ReturController::class, 'cariPembelian'])->name('retur.cari-beli');
    Route::post('retur/penjualan', [ReturController::class, 'storePenjualan'])->name('retur.store-jual');
    Route::post('retur/pembelian', [ReturController::class, 'storePembelian'])->name('retur.store-beli');

    // Audit log
    Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
    Route::get('audit/data', [AuditController::class, 'data'])->name('audit.data');

    // Pengalih sekolah aktif (super admin)
    Route::post('sekolah-aktif', [SekolahAktifController::class, 'update'])->name('sekolah-aktif.update');

    // Pengaturan
    Route::get('pengaturan', [PengaturanController::class, 'index'])->name('pengaturan.index');
    Route::get('pengaturan/backup', [PengaturanController::class, 'backup'])->name('pengaturan.backup');
    Route::match(['put', 'post'], 'pengaturan/sekolah/{id}', [PengaturanController::class, 'updateSekolah'])->name('pengaturan.sekolah');
    Route::get('pengaturan/password', [PengaturanController::class, 'editPassword'])->name('pengaturan.password.edit');
    Route::post('pengaturan/password', [PengaturanController::class, 'updatePassword'])->name('pengaturan.password');
});

require __DIR__.'/settings.php';
