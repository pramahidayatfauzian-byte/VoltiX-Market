<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id('id_role');

            $table->enum('nama_role', [
                'super admin',
                'admin',
                'kasir',
                'developer',
                ''
            ])->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};