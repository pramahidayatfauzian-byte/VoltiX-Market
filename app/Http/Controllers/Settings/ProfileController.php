<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     *
     * POS SEKOLAH: profil bawaan (name/email) tidak dipakai karena db_zian
     * memakai tb_user. Pengaturan akun ada di halaman Pengaturan & User.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        abort(403, 'Pengaturan profil tersedia di halaman Pengaturan.');

        return to_route('pengaturan.index');
    }

    /**
     * Delete the user's profile.
     *
     * POS SEKOLAH: hapus akun sendiri dilarang (penghapusan lewat
     * Manajemen User oleh admin, dan bersifat soft-delete).
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        abort(403, 'Penghapusan akun dilakukan admin lewat halaman User.');

        return redirect('/');
    }
}
