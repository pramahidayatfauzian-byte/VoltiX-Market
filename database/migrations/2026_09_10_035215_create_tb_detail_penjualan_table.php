<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_detail_penjualan', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('id_penjualan')->nullable();
            $table->unsignedBigInteger('id_barang')->nullable();

            $table->integer('jumlah_barang')->nullable();

            $table->decimal('harga_beli', 12, 2)->nullable();
            $table->decimal('harga_jual', 12, 2)->nullable();

            $table->enum('diskon_tipe', [
                'persen',
                'nominal'
            ])->nullable();

            $table->decimal('diskon_nilai', 12, 2)->nullable();
            $table->decimal('diskon_nominal', 12, 2)->nullable();

            $table->decimal('subtotal', 14, 2)->nullable();

            $table->foreign('id_penjualan')
                ->references('id_penjualan')
                ->on('tb_penjualan')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('id_barang')
                ->references('id_barang')
                ->on('tb_barang')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_detail_penjualan');
    }
};