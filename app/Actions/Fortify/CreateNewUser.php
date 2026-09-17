<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Registrasi mandiri dimatikan: POS SEKOLAH memakai tb_user yang
     * dikelola lewat halaman Manajemen User (tanpa tabel `users`/email).
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        abort(404, 'Registrasi mandiri tidak tersedia. Hubungi administrator.');
    }
}
