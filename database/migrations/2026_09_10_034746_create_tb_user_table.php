<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_user', function (Blueprint $table) {
            $table->id('id_user');

            $table->unsignedBigInteger('id_sekolah')->nullable();
            $table->unsignedBigInteger('id_role')->nullable();

            $table->string('username', 50)->nullable();
            $table->string('password', 255)->nullable();
            $table->string('nama_lengkap', 100)->nullable();

            $table->boolean('is_active')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->integer('created_by')->nullable();

            $table->timestamp('updated_at')->nullable();
            $table->integer('updated_by')->nullable();

            $table->timestamp('deleted_at')->nullable();
            $table->integer('deleted_by')->nullable();

            $table->foreign('id_sekolah')
                ->references('id_sekolah')
                ->on('tb_sekolah')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreign('id_role')
                ->references('id_role')
                ->on('roles')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_user');
    }
};