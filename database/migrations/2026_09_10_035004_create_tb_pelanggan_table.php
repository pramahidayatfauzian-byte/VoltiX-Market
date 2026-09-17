<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_pelanggan', function (Blueprint $table) {
            $table->id('id_pelanggan');

            $table->unsignedBigInteger('id_kelompok_pelanggan')->nullable();

            $table->string('nama_pelanggan', 150)->nullable();
            $table->string('telepon', 20)->nullable();
            $table->text('alamat')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->integer('created_by')->nullable();

            $table->timestamp('updated_at')->nullable();
            $table->integer('updated_by')->nullable();

            $table->timestamp('deleted_at')->nullable();
            $table->integer('deleted_by')->nullable();

            $table->boolean('is_delete')->nullable();

            $table->foreign('id_kelompok_pelanggan')
                ->references('id')
                ->on('tb_kelompok_pelanggan')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_pelanggan');
    }
};