<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fitur foto produk & logo sekolah (no. 6):
     * path file relatif terhadap disk `public` (storage/app/public).
     */
    public function up(): void
    {
        Schema::table('tb_barang', function (Blueprint $table) {
            $table->string('foto', 255)->nullable()->after('barcode');
        });

        Schema::table('tb_sekolah', function (Blueprint $table) {
            $table->string('logo', 255)->nullable()->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('tb_barang', function (Blueprint $table) {
            $table->dropColumn('foto');
        });

        Schema::table('tb_sekolah', function (Blueprint $table) {
            $table->dropColumn('logo');
        });
    }
};
