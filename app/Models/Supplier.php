<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Supplier extends Model
{
    protected $table = 'tb_supplier';
    protected $primaryKey = 'id_supplier';
    public $timestamps = false;

    protected $fillable = [
        'id_sekolah', 'nama', 'no_telepon', 'alamat_supplier',
        'created_at', 'created_by',
        'deleted_at', 'deleted_by', 'is_delete',
    ];

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'id_sekolah', 'id_sekolah');
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
