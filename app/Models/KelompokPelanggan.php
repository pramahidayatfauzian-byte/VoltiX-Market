<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KelompokPelanggan extends Model
{
    protected $table = 'tb_kelompok_pelanggan';
    public $timestamps = false;

    protected $fillable = ['id_sekolah', 'nama_kelompok'];
}
