<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pelanggan extends Model
{
    protected $table = 'tb_pelanggan';
    protected $primaryKey = 'id_pelanggan';
    public $timestamps = false;

    protected $fillable = [
        'id_kelompok_pelanggan',
        'nama_pelanggan',
        'telepon',
        'alamat',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
        'deleted_by',
        'is_delete',
    ];

    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('is_delete')->orWhere('is_delete', 0);
        })->whereNull('deleted_at');
    }

    public function kelompok(): BelongsTo
    {
        return $this->belongsTo(KelompokPelanggan::class, 'id_kelompok_pelanggan', 'id');
    }
}
