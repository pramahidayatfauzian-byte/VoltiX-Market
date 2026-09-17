<?php

namespace App\Support;

use App\Models\Sekolah;
use Illuminate\Http\Request;

/**
 * Scope tenant aktif.
 *
 * - Developer: akses global penuh. Boleh memilih sekolah aktif
 *   (session 'sekolah_aktif_id') atau 'semua' untuk seluruh data.
 * - Super admin / admin / kasir: SELALU terkunci di sekolah sendiri
 *   (isolasi tenant). Super admin = full fitur seperti admin,
 *   hanya tidak bisa pindah sekolah / lihat semua.
 *
 * @return array{0: ?int, 1: bool} [id_sekolah_aktif, lihat_semua]
 *   $lihatSemua true hanya untuk developer tanpa sekolah dipilih.
 */
class Tenant
{
    /** Akses global penuh (semua sekolah). */
    public static function isDeveloper(Request $request): bool
    {
        return ($request->user()->role?->nama_role === 'developer');
    }

    /** Super admin per-sekolah (full fitur, terkunci di sekolah sendiri). */
    public static function isSuperAdmin(Request $request): bool
    {
        return ($request->user()->role?->nama_role === 'super admin');
    }

    public static function resolve(Request $request): array
    {
        $user = $request->user();

        if (! self::isDeveloper($request)) {
            return [(int) $user->id_sekolah, false];
        }

        $aktif = $request->session()->get('sekolah_aktif_id', 'semua');

        if ($aktif === 'semua' || $aktif === null) {
            return [null, true];
        }

        return [(int) $aktif, false];
    }

    /** Daftar sekolah untuk dropdown header. */
    public static function daftar(Request $request)
    {
        $user = $request->user();

        if (! self::isDeveloper($request)) {
            return Sekolah::where('id_sekolah', $user->id_sekolah)
                ->get(['id_sekolah', 'kode_sekolah', 'nama_sekolah']);
        }

        return Sekolah::where('is_active', true)
            ->orderBy('nama_sekolah')
            ->get(['id_sekolah', 'kode_sekolah', 'nama_sekolah']);
    }

    /** Info sekolah aktif untuk dibagikan ke frontend. */
    public static function aktif(Request $request): array
    {
        [$id, $semua] = self::resolve($request);

        if ($semua || ! $id) {
            return ['id_sekolah' => null, 'nama_sekolah' => 'Semua Sekolah'];
        }

        $sekolah = Sekolah::find($id);

        return [
            'id_sekolah' => $id,
            'nama_sekolah' => $sekolah?->nama_sekolah ?? 'Sekolah #'.$id,
            'logo_url' => $sekolah?->logo ? asset('storage/'.$sekolah->logo) : null,
        ];
    }
}
