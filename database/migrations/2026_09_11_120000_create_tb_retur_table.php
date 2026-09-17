<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fitur retur (no. 3): satu tabel untuk retur penjualan
     * (barang kembali, stok bertambah) dan retur pembelian
     * (barang dikembalikan ke supplier, stok berkurang).
     * Total faktur asli TIDAK diubah (integritas riwayat).
     */
    public function up(): void
    {
        Schema::create('tb_retur', function (Blueprint $table) {
            // Catatan: kolom FK memakai integer (signed) agar cocok dengan
            // tipe INT pada tb_sekolah/tb_barang/tb_user di db_zian.
            $table->id('id_retur');

            $table->integer('id_sekolah')->nullable();
            $table->enum('tipe', ['penjualan', 'pembelian']);
            $table->integer('id_referensi')->nullable()
                ->comment('id_penjualan atau id_pembelian');
            $table->integer('id_barang')->nullable();

            $table->integer('jumlah')->nullable();
            $table->decimal('harga', 12, 2)->nullable();
            $table->decimal('subtotal', 14, 2)->nullable();
            $table->string('alasan', 255)->nullable();

            $table->dateTime('tanggal_retur')->nullable();

            $table->integer('id_user')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->integer('created_by')->nullable();

            $table->foreign('id_sekolah')
                ->references('id_sekolah')
                ->on('tb_sekolah')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('id_barang')
                ->references('id_barang')
                ->on('tb_barang')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('id_user')
                ->references('id_user')
                ->on('tb_user')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->index(['tipe', 'id_referensi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_retur');
    }
};
