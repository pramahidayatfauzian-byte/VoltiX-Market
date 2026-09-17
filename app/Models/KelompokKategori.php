<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KelompokKategori extends Model
{
    protected $table = 'tb_kelompok_kategori';
    public $timestamps = false;

    protected $fillable = ['id_sekolah', 'nama_kelompok', 'created_at', 'created_by'];

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'id_sekolah', 'id_sekolah');
    }

    public function kategori(): HasMany
    {
        return $this->hasMany(Kategori::class, 'id_kelompok', 'id');
    }

    public function scopeTenant($query, ?int $idSekolah, bool $isSuperAdmin = false)
    {
        if ($isSuperAdmin || ! $idSekolah) {
            return $query;
        }

        return $query->where($this->getTable().'.id_sekolah', $idSekolah);
    }
}
