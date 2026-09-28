<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kategori extends Model
{
    protected $table = 'tb_kategori';
    protected $primaryKey = 'id_kategori';
    public $timestamps = false;

    protected $fillable = [
        'id_kelompok', 'nama',
        'created_at', 'created_by',
        'updated_at', 'updated_by',
        'deleted_at', 'deleted_by', 'is_delete',
    ];

    public function kelompok(): BelongsTo
    {
        return $this->belongsTo(KelompokKategori::class, 'id_kelompok', 'id');
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

        // Kategori milik tenant jika tanpa kelompok (shared), kelompoknya milik
        // tenant, ATAU kelompoknya global (milik bersama semua sekolah).
        return $query->where(function ($q) use ($idSekolah) {
            $q->whereNull('tb_kategori.id_kelompok')
                ->orWhereIn('tb_kategori.id_kelompok', function ($sub) use ($idSekolah) {
                    $sub->select('id')->from('tb_kelompok_kategori')
                        ->where(function ($w) use ($idSekolah) {
                            $w->where('id_sekolah', $idSekolah)->orWhereNull('id_sekolah');
                        });
                });
        });
    }
}
