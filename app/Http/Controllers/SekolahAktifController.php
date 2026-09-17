<?php

namespace App\Http\Controllers;

use App\Support\Tenant;
use Illuminate\Http\Request;

class SekolahAktifController extends Controller
{
    /**
     * POST /sekolah-aktif — developer memilih konteks sekolah.
     * id_sekolah: integer, atau 'semua' untuk seluruh tenant.
     * Super admin/admin/kasir ditolak (terkunci di sekolah sendiri).
     */
    public function update(Request $request)
    {
        if (! Tenant::isDeveloper($request)) {
            return back()->withErrors(['sekolah' => 'Hanya developer yang bisa berpindah sekolah.']);
        }

        $v = $request->validate([
            'id_sekolah' => ['required'],
        ]);

        if ($v['id_sekolah'] === 'semua' || $v['id_sekolah'] === null) {
            $request->session()->put('sekolah_aktif_id', 'semua');
        } else {
            $request->validate(['id_sekolah' => ['integer', 'exists:tb_sekolah,id_sekolah']]);
            $request->session()->put('sekolah_aktif_id', (int) $v['id_sekolah']);
        }

        return back()->with('success', 'Sekolah aktif diperbarui.');
    }
}
