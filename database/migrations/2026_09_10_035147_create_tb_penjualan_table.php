<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_penjualan', function (Blueprint $table) {
            $table->id('id_penjualan');

            $table->unsignedBigInteger('id_sekolah')->nullable();
            $table->unsignedBigInteger('id_user')->nullable();
            $table->unsignedBigInteger('id_pelanggan')->nullable();

            $table->dateTime('tanggal_penjualan')->nullable();

            $table->decimal('total_faktur', 14, 2)->nullable();
            $table->decimal('total_bayar', 14, 2)->nullable();
            $table->decimal('kembalian', 14, 2)->nullable();

            $table->enum('status_pembayaran', [
                'sudah bayar',
                'belum bayar'
            ])->nullable();

            $table->enum('jenis_transaksi', [
                'tunai',
                'kredit'
            ])->nullable();

            $table->string('cara_bayar', 50)->nullable();
            $table->text('note')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->integer('created_by')->nullable();

            $table->timestamp('deleted_at')->nullable();
            $table->integer('deleted_by')->nullable();

            $table->boolean('is_delete')->nullable();

            $table->foreign('id_sekolah')
                ->references('id_sekolah')
                ->on('tb_sekolah')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('id_user')
                ->references('id_user')
                ->on('tb_user')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('id_pelanggan')
                ->references('id_pelanggan')
                ->on('tb_pelanggan')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_penjualan');
    }
};