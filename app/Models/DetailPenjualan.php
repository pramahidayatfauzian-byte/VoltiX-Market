<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailPenjualan extends Model
{
    protected $table = 'tb_detail_penjualan';
    public $timestamps = false;

    protected $fillable = [
        'id_penjualan',
        'id_barang',
        'jumlah_barang',
        'harga_beli',
        'harga_jual',
        'diskon_tipe',
        'diskon_nilai',
        'diskon_nominal',
        'subtotal',
    ];

    public function penjualan(): BelongsTo
    {
        return $this->belongsTo(Penjualan::class, 'id_penjualan', 'id_penjualan');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }
}
