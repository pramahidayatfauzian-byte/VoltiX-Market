<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Penjualan extends Model
{
    protected $table = 'tb_penjualan';
    protected $primaryKey = 'id_penjualan';
    public $timestamps = false;

    protected $fillable = [
        'id_sekolah',
        'id_user',
        'id_pelanggan',
        'tanggal_penjualan',
        'total_faktur',
        'total_bayar',
        'kembalian',
        'status_pembayaran',
        'jenis_transaksi',
        'cara_bayar',
        'note',
        'created_at',
        'created_by',
        'deleted_at',
        'deleted_by',
        'is_delete',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_penjualan' => 'datetime',
            'total_faktur' => 'decimal:2',
            'total_bayar' => 'decimal:2',
            'kembalian' => 'decimal:2',
        ];
    }

    public function kasir(): BelongsTo
    {
        return $this->belongsTo(TbUser::class, 'id_user', 'id_user');
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class, 'id_pelanggan', 'id_pelanggan');
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'id_sekolah', 'id_sekolah');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(DetailPenjualan::class, 'id_penjualan', 'id_penjualan');
    }

    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('is_delete')->orWhere('is_delete', 0);
        })->whereNull('deleted_at');
    }

    public function scopeTenant($query, ?int $idSekolah, bool $isSuperAdmin = false)
    {
        if ($isSuperAdmin || ! $idSekolah) {
            return $query;
        }

        return $query->where($this->getTable().'.id_sekolah', $idSekolah);
    }
}
