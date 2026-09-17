<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Retur extends Model
{
    protected $table = 'tb_retur';
    protected $primaryKey = 'id_retur';
    public $timestamps = false;

    protected $fillable = [
        'id_sekolah', 'tipe', 'id_referensi', 'id_barang',
        'jumlah', 'harga', 'subtotal', 'alasan',
        'tanggal_retur', 'id_user', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_retur' => 'datetime',
            'harga' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'id_sekolah', 'id_sekolah');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(TbUser::class, 'id_user', 'id_user');
    }

    public function scopeTenant($query, ?int $idSekolah, bool $isSuperAdmin = false)
    {
        if ($isSuperAdmin || ! $idSekolah) {
            return $query;
        }

        return $query->where($this->getTable().'.id_sekolah', $idSekolah);
    }
}
