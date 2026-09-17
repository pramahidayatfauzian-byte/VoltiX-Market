<?php

namespace App\Http\Controllers;

use App\Models\Kategori;
use App\Models\KelompokKategori;
use Illuminate\Http\Request;

class KategoriController extends Controller
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

    /** GET /kategori/data */
    public function data(Request $request)
    {
        $this->authorizeAkses($request);
        $query = Kategori::valid()
            ->with('kelompok:id,nama_kelompok,id_sekolah')
            ->tenant($request->user()->id_sekolah, $this->isDeveloper($request))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->query('search');
                $q->where('nama', 'like', "%{$s}%");
            })
            ->orderBy('nama');

        return response()->json($query->paginate(10)->withQueryString());
    }

    public function store(Request $request)
    {
        $this->authorizeManage($request);
        $v = $request->validate([
            'id_kelompok' => ['nullable', 'integer', 'exists:tb_kelompok_kategori,id'],
            'nama' => ['required', 'string', 'max:100'],
        ]);

        if (! $this->isDeveloper($request) && ! empty($v['id_kelompok'])) {
            $ok = KelompokKategori::tenant($request->user()->id_sekolah, false)
                ->where('id', $v['id_kelompok'])->exists();
            if (! $ok) {
                return back()->withErrors(['id_kelompok' => 'Kelompok tidak valid untuk tenant ini.']);
            }
        }

        Kategori::create($v + [
            'created_by' => $request->user()->id_user,
            'is_delete' => 0,
        ]);

        return back()->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function update(Request $request, int $id)
    {
        $this->authorizeManage($request);
        $kategori = Kategori::valid()
            ->tenant($request->user()->id_sekolah, $this->isDeveloper($request))
            ->findOrFail($id);

        $v = $request->validate([
            'id_kelompok' => ['nullable', 'integer', 'exists:tb_kelompok_kategori,id'],
            'nama' => ['required', 'string', 'max:100'],
        ]);

        if (! $this->isDeveloper($request) && ! empty($v['id_kelompok'])) {
            $ok = KelompokKategori::tenant($request->user()->id_sekolah, false)
                ->where('id', $v['id_kelompok'])->exists();
            if (! $ok) {
                return back()->withErrors(['id_kelompok' => 'Kelompok tidak valid untuk tenant ini.']);
            }
        }

        $kategori->update($v + ['updated_by' => $request->user()->id_user]);

        return back()->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $this->authorizeManage($request);
        $kategori = Kategori::valid()
            ->tenant($request->user()->id_sekolah, $this->isDeveloper($request))
            ->findOrFail($id);

        if (\App\Models\Barang::where('id_kategori', $id)->valid()->exists()) {
            return back()->withErrors(['kategori' => 'Kategori masih dipakai produk, tidak bisa dihapus.']);
        }

        $kategori->update([
            'is_delete' => 1,
            'deleted_at' => now(),
            'deleted_by' => $request->user()->id_user,
        ]);

        return back()->with('success', 'Kategori berhasil dihapus.');
    }
}
