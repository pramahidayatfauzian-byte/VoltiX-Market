<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_kategori', function (Blueprint $table) {
            $table->id('id_kategori');

            $table->unsignedBigInteger('id_kelompok')->nullable();
            $table->string('nama', 100)->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->integer('created_by')->nullable();

            $table->timestamp('updated_at')->nullable();
            $table->integer('updated_by')->nullable();

            $table->timestamp('deleted_at')->nullable();
            $table->integer('deleted_by')->nullable();

            $table->boolean('is_delete')->nullable();

            $table->foreign('id_kelompok')
                ->references('id')
                ->on('tb_kelompok_kategori')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_kategori');
    }
};