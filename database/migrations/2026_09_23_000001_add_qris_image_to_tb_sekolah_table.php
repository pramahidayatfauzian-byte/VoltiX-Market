<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_sekolah', function (Blueprint $table) {
            $table->string('qris_image', 200)->nullable()->after('logo');
        });
    }

    public function down(): void
    {
        Schema::table('tb_sekolah', function (Blueprint $table) {
            $table->dropColumn('qris_image');
        });
    }
};
