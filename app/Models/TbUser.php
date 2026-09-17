<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class TbUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'tb_user';
    protected $primaryKey = 'id_user';

    protected $fillable = [
        'id_sekolah',
        'id_role',
        'username',
        'password',
        'nama_lengkap',
        'is_active',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
        'deleted_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'id_sekolah', 'id_sekolah');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'id_role', 'id_role');
    }

    public function scopeValid($query)
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeTenant($query, ?int $idSekolah, bool $isSuperAdmin = false)
    {
        if ($isSuperAdmin || ! $idSekolah) {
            return $query;
        }

        return $query->where($this->getTable().'.id_sekolah', $idSekolah);
    }

    public function getRoleNameAttribute(): ?string
    {
        return $this->role?->nama_role;
    }

    public function isDeveloper(): bool
    {
        return $this->role?->nama_role === 'developer';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->nama_role === 'super admin';
    }

    public function isAdmin(): bool
    {
        return $this->role?->nama_role === 'admin';
    }

    public function isKasir(): bool
    {
        return $this->role?->nama_role === 'kasir';
    }
}
