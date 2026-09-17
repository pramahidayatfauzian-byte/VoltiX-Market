<?php

namespace App\Http\Controllers;

use App\Models\Kategori;
use App\Models\KelompokKategori;
use Illuminate\Http\Request;

class KelompokKategoriController extends Controller
{
    private function isDeveloper(Request $request): bool
    {
        return ($request->user()->role?->nama_role === 'developer');
    }

    private function authorizeAkses(Request $request): void
    {
        // Semua role boleh akses (developer global, super admin/admin/kasir per-sekolah).
    }

    private function authorizeManage(Request $request): void
    {
        $this->authorizeAkses($request);
        if (($request->user()->role?->nama_role ?? '') === 'kasir') {
            abort(403, 'Kasir hanya boleh melihat produk.');
        }
    }

    /** GET /kelompok-kategori/data */
    public function data(Request $request)
    {
        $this->authorizeAkses($request);
        $query = KelompokKategori::with('sekolah:id_sekolah,nama_sekolah')
            ->tenant($request->user()->id_sekolah, $this->isDeveloper($request))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->query('search');
                $q->where('nama_kelompok', 'like', "%{$s}%");
            })
            ->orderBy('nama_kelompok');

        return response()->json($query->paginate(10)->withQueryString());
    }

    public function store(Request $request)
    {
        $this->authorizeManage($request);
        $v = $request->validate([
            'id_sekolah' => ['nullable', 'integer', 'exists:tb_sekolah,id_sekolah'],
            'nama_kelompok' => ['required', 'string', 'max:100'],
        ]);

        KelompokKategori::create([
            'id_sekolah' => $this->isDeveloper($request)
                ? ($v['id_sekolah'] ?? $request->user()->id_sekolah)
                : $request->user()->id_sekolah,
            'nama_kelompok' => $v['nama_kelompok'],
            'created_by' => $request->user()->id_user,
        ]);

        return back()->with('success', 'Kelompok kategori berhasil ditambahkan.');
    }

    public function update(Request $request, int $id)
    {
        $this->authorizeManage($request);
        $kelompok = KelompokKategori::tenant($request->user()->id_sekolah, $this->isDeveloper($request))
            ->findOrFail($id);

        $v = $request->validate([
            'nama_kelompok' => ['required', 'string', 'max:100'],
        ]);

        $kelompok->update($v);

        return back()->with('success', 'Kelompok kategori berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $this->authorizeManage($request);
        $kelompok = KelompokKategori::tenant($request->user()->id_sekolah, $this->isDeveloper($request))
            ->findOrFail($id);

        if (Kategori::where('id_kelompok', $id)->valid()->exists()) {
            return back()->withErrors(['kelompok' => 'Kelompok masih dipakai kategori, tidak bisa dihapus.']);
        }
        if (\App\Models\Barang::where('id_kelompok_kategori', $id)->valid()->exists()) {
            return back()->withErrors(['kelompok' => 'Kelompok masih dipakai produk, tidak bisa dihapus.']);
        }

        $kelompok->delete();

        return back()->with('success', 'Kelompok kategori berhasil dihapus.');
    }
}
