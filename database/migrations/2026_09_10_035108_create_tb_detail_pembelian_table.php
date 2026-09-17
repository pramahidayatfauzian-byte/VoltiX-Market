<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_detail_pembelian', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('id_pembelian')->nullable();
            $table->unsignedBigInteger('id_barang')->nullable();

            $table->string('satuan', 20)->nullable();
            $table->integer('jumlah')->nullable();

            $table->decimal('harga_beli', 12, 2)->nullable();
            $table->decimal('subtotal', 14, 2)->nullable();

            $table->foreign('id_pembelian')
                ->references('id_pembelian')
                ->on('tb_pembelian')
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
        Schema::dropIfExists('tb_detail_pembelian');
    }
};