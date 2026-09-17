<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_kelompok_kategori', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('id_sekolah')->nullable();
            $table->string('nama_kelompok', 100)->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->integer('created_by')->nullable();

            $table->foreign('id_sekolah')
                ->references('id_sekolah')
                ->on('tb_sekolah')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_kelompok_kategori');
    }
};