<?php

namespace App\Http\Controllers;

use App\Models\Pembelian;
use App\Models\Supplier;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierController extends Controller
{
    private function isDeveloper(Request $request): bool
    {
        return ($request->user()->role?->nama_role === 'developer');
    }

    private function authorizeManage(Request $request): void
    {
        $role = $request->user()->role?->nama_role;
        if (! in_array($role, ['developer', 'super admin', 'admin'], true)) {
            abort(403, 'Anda tidak memiliki akses ke pembelian & supplier.');
        }
    }

    /** GET /supplier — halaman standalone */
    public function index(Request $request)
    {
        $this->authorizeManage($request);
        $user = $request->user()->loadMissing(['sekolah', 'role']);
        $isDeveloper = $this->isDeveloper($request);

        $sekolahList = $isDeveloper
            ? \App\Models\Sekolah::where('is_active', true)->orderBy('nama_sekolah')->get(['id_sekolah', 'nama_sekolah'])
            : collect();

        return Inertia::render('Supplier', [
            'sekolah' => $user->sekolah ? [
                'id_sekolah' => $user->sekolah->id_sekolah,
                'nama_sekolah' => $user->sekolah->nama_sekolah,
            ] : Tenant::aktif($request),
            'sekolah_list' => $sekolahList,
            'is_super_admin' => $isDeveloper,
        ]);
    }

    /** GET /supplier/data — JSON search + pagination */
    public function data(Request $request)
    {
        $this->authorizeManage($request);

        $query = Supplier::valid()
            ->with('sekolah:id_sekolah,nama_sekolah')
            ->tenant($request->user()->id_sekolah, $this->isDeveloper($request))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->query('search');
                $q->where(function ($w) use ($s) {
                    $w->where('nama', 'like', "%{$s}%")
                        ->orWhere('no_telepon', 'like', "%{$s}%");
                });
            })
            ->orderBy('nama');

        return response()->json($query->paginate(10)->withQueryString());
    }

    public function store(Request $request)
    {
        $this->authorizeManage($request);

        $v = $request->validate([
            'id_sekolah' => ['nullable', 'integer', 'exists:tb_sekolah,id_sekolah'],
            'nama' => ['required', 'string', 'max:100'],
            'no_telepon' => ['nullable', 'string', 'max:20'],
            'alamat_supplier' => ['nullable', 'string', 'max:1000'],
        ]);

        Supplier::create([
            'id_sekolah' => $this->isDeveloper($request)
                ? ($v['id_sekolah'] ?? $request->user()->id_sekolah)
                : $request->user()->id_sekolah,
            'nama' => $v['nama'],
            'no_telepon' => $v['no_telepon'] ?? null,
            'alamat_supplier' => $v['alamat_supplier'] ?? null,
            'created_by' => $request->user()->id_user,
            'is_delete' => 0,
        ]);

        return back()->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function update(Request $request, int $id)
    {
        $this->authorizeManage($request);

        $supplier = Supplier::valid()
            ->tenant($request->user()->id_sekolah, $this->isDeveloper($request))
            ->findOrFail($id);

        $v = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'no_telepon' => ['nullable', 'string', 'max:20'],
            'alamat_supplier' => ['nullable', 'string', 'max:1000'],
        ]);

        $supplier->update($v);

        return back()->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $this->authorizeManage($request);

        $supplier = Supplier::valid()
            ->tenant($request->user()->id_sekolah, $this->isDeveloper($request))
            ->findOrFail($id);

        if (Pembelian::where('id_supplier', $id)->valid()->exists()) {
            return back()->withErrors(['supplier' => 'Supplier masih dipakai pembelian, tidak bisa dihapus.']);
        }
        if (\App\Models\Barang::where('id_supplier', $id)->valid()->exists()) {
            return back()->withErrors(['supplier' => 'Supplier masih dipakai produk, tidak bisa dihapus.']);
        }

        $supplier->update([
            'is_delete' => 1,
            'deleted_at' => now(),
            'deleted_by' => $request->user()->id_user,
        ]);

        return back()->with('success', 'Supplier berhasil dihapus.');
    }
}
