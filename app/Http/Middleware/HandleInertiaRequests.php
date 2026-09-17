<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        if ($user) {
            $user->loadMissing(['sekolah:id_sekolah,kode_sekolah,nama_sekolah', 'role:id_role,nama_role']);
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id_user' => $user->id_user,
                    'username' => $user->username,
                    'nama_lengkap' => $user->nama_lengkap,
                    'id_sekolah' => $user->id_sekolah,
                    'id_role' => $user->id_role,
                    'role' => $user->role?->nama_role,
                    'sekolah' => $user->sekolah ? [
                        'id_sekolah' => $user->sekolah->id_sekolah,
                        'kode_sekolah' => $user->sekolah->kode_sekolah,
                        'nama_sekolah' => $user->sekolah->nama_sekolah,
                        'logo_url' => $user->sekolah->logo ? asset('storage/'.$user->sekolah->logo) : null,
                    ] : null,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'receipt' => fn () => $request->session()->get('receipt'),
            ],
            'sekolah_aktif' => fn () => $user ? \App\Support\Tenant::aktif($request) : null,
            'daftar_sekolah' => fn () => $user ? \App\Support\Tenant::daftar($request) : [],
            'bisa_pindah_sekolah' => fn () => $user ? \App\Support\Tenant::isDeveloper($request) : false,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
