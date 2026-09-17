<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pembelian extends Model
{
    protected $table = 'tb_pembelian';
    protected $primaryKey = 'id_pembelian';
    public $timestamps = false;

    protected $fillable = [
        'id_sekolah', 'id_supplier', 'id_user',
        'nomor_faktur', 'tanggal_faktur', 'total_bayar',
        'status_pembelian', 'jenis_transaksi', 'cara_bayar', 'note',
        'created_at', 'created_by',
        'deleted_at', 'deleted_by', 'is_delete',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_faktur' => 'datetime',
            'total_bayar' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'id_supplier', 'id_supplier');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(TbUser::class, 'id_user', 'id_user');
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'id_sekolah', 'id_sekolah');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(DetailPembelian::class, 'id_pembelian', 'id_pembelian');
    }

    public function scopeValid($query)
    {
        // Catatan: kolom deleted_at tb_pembelian punya DEFAULT CURRENT_TIMESTAMP
        // sehingga selalu terisi; validitas hanya ditentukan is_delete.
        return $query->where(function ($q) {
            $q->whereNull('is_delete')->orWhere('is_delete', 0);
        });
    }

    public function scopeTenant($query, ?int $idSekolah, bool $isSuperAdmin = false)
    {
        if ($isSuperAdmin || ! $idSekolah) {
            return $query;
        }

        return $query->where($this->getTable().'.id_sekolah', $idSekolah);
    }
}
