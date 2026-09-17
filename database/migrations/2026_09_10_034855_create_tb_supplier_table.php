<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_supplier', function (Blueprint $table) {
            $table->id('id_supplier');

            $table->unsignedBigInteger('id_sekolah')->nullable();
            $table->string('nama', 100)->nullable();
            $table->string('no_telepon', 20)->nullable();
            $table->text('alamat_supplier')->nullable();

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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_supplier');
    }
};