<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_pembelian', function (Blueprint $table) {
            $table->id('id_pembelian');

            $table->unsignedBigInteger('id_sekolah')->nullable();
            $table->unsignedBigInteger('id_supplier')->nullable();
            $table->unsignedBigInteger('id_user')->nullable();

            $table->string('nomor_faktur', 50)->nullable();
            $table->dateTime('tanggal_faktur')->nullable();

            $table->decimal('total_bayar', 14, 2)->nullable();

            $table->enum('status_pembelian', [
                'draft',
                'selesai'
            ])->nullable();

            $table->enum('jenis_transaksi', [
                'tunai',
                'kredit'
            ])->nullable();

            $table->string('cara_bayar', 50)->nullable();
            $table->text('note')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->integer('created_by')->nullable();

            $table->timestamp('deleted_at')->useCurrent();
            $table->integer('deleted_by')->nullable();

            $table->boolean('is_delete')->nullable();

            $table->foreign('id_sekolah')
                ->references('id_sekolah')
                ->on('tb_sekolah')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('id_supplier')
                ->references('id_supplier')
                ->on('tb_supplier')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('id_user')
                ->references('id_user')
                ->on('tb_user')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_pembelian');
    }
};