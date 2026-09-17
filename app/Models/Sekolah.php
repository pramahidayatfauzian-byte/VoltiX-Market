<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sekolah extends Model
{
    protected $table = 'tb_sekolah';
    protected $primaryKey = 'id_sekolah';
    public $timestamps = false;

    protected $fillable = [
        'kode_sekolah',
        'nama_sekolah',
        'alamat_sekolah',
        'website',
        'logo',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(TbUser::class, 'id_sekolah', 'id_sekolah');
    }
}
