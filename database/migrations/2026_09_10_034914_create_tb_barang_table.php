<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_barang', function (Blueprint $table) {
            $table->id('id_barang');

            $table->unsignedBigInteger('id_sekolah')->nullable();
            $table->string('barcode', 50)->nullable();
            $table->string('nama', 150)->nullable();

            $table->unsignedBigInteger('id_kategori')->nullable();
            $table->unsignedBigInteger('id_kelompok_kategori')->nullable();
            $table->unsignedBigInteger('id_supplier')->nullable();

            $table->string('satuan', 20)->nullable();

            $table->decimal('harga_beli', 12, 2)->nullable();
            $table->decimal('harga_jual', 12, 2)->nullable();

            $table->integer('stok')->nullable();
            $table->boolean('is_active')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->integer('created_by')->nullable();

            $table->timestamp('updated_at')->nullable();
            $table->integer('updated_by')->nullable();

            $table->timestamp('deleted_at')->nullable();
            $table->integer('deleted_by')->nullable();

            $table->boolean('is_delete')->nullable();

            $table->foreign('id_sekolah')
                ->references('id_sekolah')
                ->on('tb_sekolah')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('id_kategori')
                ->references('id_kategori')
                ->on('tb_kategori')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('id_kelompok_kategori')
                ->references('id')
                ->on('tb_kelompok_kategori')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('id_supplier')
                ->references('id_supplier')
                ->on('tb_supplier')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_barang');
    }
};