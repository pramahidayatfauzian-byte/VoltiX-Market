<?php

namespace App\Http\Controllers;

use App\Models\KelompokPelanggan;
use App\Models\Pelanggan;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PelangganController extends Controller
{
    private function isDeveloper(Request $request): bool
    {
        return ($request->user()->role?->nama_role === 'developer');
    }

    private function authorizeAkses(Request $request): void
    {
        // Semua role boleh akses (developer global, super admin/admin/kasir per-sekolah).
    }

    private function baseQuery(Request $request)
    {
        $user = $request->user();
        $isDeveloper = $this->isDeveloper($request);

        return Pelanggan::valid()
            ->with('kelompok:id,nama_kelompok')
            ->when(! $isDeveloper && $user->id_sekolah, function ($q) use ($user) {
                $q->where(function ($w) use ($user) {
                    $w->whereNull('id_kelompok_pelanggan')
                        ->orWhereIn('id_kelompok_pelanggan', function ($sub) use ($user) {
                            $sub->select('id')->from('tb_kelompok_pelanggan')
                                ->where('id_sekolah', $user->id_sekolah);
                        });
                });
            });
    }

    /** GET /pelanggan — halaman standalone */
    public function page(Request $request)
    {
        $this->authorizeAkses($request);
        $request->user()->loadMissing(['sekolah', 'role']);
        [$sekolahId, $semua] = Tenant::resolve($request);

        $kelompokQuery = KelompokPelanggan::query();
        if (! $semua && $sekolahId) {
            $kelompokQuery->where('id_sekolah', $sekolahId);
        }
        $kelompok = $kelompokQuery->orderBy('nama_kelompok')->get(['id', 'id_sekolah', 'nama_kelompok']);

        return Inertia::render('Pelanggan', [
            'sekolah' => Tenant::aktif($request),
            'kelompok_pelanggan' => $kelompok,
        ]);
    }

    /** GET /pelanggan/data (& legacy /kasir/pelanggan) — JSON paginated */
    public function index(Request $request)
    {
        $this->authorizeAkses($request);
        $search = trim((string) $request->query('search', ''));

        $query = $this->baseQuery($request)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('nama_pelanggan', 'like', "%{$search}%")
                        ->orWhere('telepon', 'like', "%{$search}%")
                        ->orWhere('alamat', 'like', "%{$search}%");
                });
            })
            ->orderBy('nama_pelanggan');

        return response()->json($query->paginate(10)->withQueryString());
    }

    public function store(Request $request)
    {
        $this->authorizeAkses($request);
        $validated = $request->validate([
            'id_kelompok_pelanggan' => ['nullable', 'integer', 'exists:tb_kelompok_pelanggan,id'],
            'nama_pelanggan' => ['required', 'string', 'max:150'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'alamat' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $this->isDeveloper($request) && ! empty($validated['id_kelompok_pelanggan'])) {
            $ok = \App\Models\KelompokPelanggan::where('id', $validated['id_kelompok_pelanggan'])
                ->where('id_sekolah', $request->user()->id_sekolah)->exists();
            if (! $ok) {
                return back()->withErrors(['id_kelompok_pelanggan' => 'Kelompok tidak valid untuk tenant ini.']);
            }
        }

        Pelanggan::create($validated + [
            'created_by' => $request->user()->id_user,
            'is_delete' => 0,
        ]);

        return back()->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    public function update(Request $request, int $id)
    {
        $this->authorizeAkses($request);
        $pelanggan = $this->baseQuery($request)->findOrFail($id);

        $validated = $request->validate([
            'id_kelompok_pelanggan' => ['nullable', 'integer', 'exists:tb_kelompok_pelanggan,id'],
            'nama_pelanggan' => ['required', 'string', 'max:150'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'alamat' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $this->isDeveloper($request) && ! empty($validated['id_kelompok_pelanggan'])) {
            $ok = \App\Models\KelompokPelanggan::where('id', $validated['id_kelompok_pelanggan'])
                ->where('id_sekolah', $request->user()->id_sekolah)->exists();
            if (! $ok) {
                return back()->withErrors(['id_kelompok_pelanggan' => 'Kelompok tidak valid untuk tenant ini.']);
            }
        }

        $pelanggan->update($validated + ['updated_by' => $request->user()->id_user]);

        return back()->with('success', 'Pelanggan berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $this->authorizeAkses($request);
        $pelanggan = $this->baseQuery($request)->findOrFail($id);
        $pelanggan->update([
            'is_delete' => 1,
            'deleted_at' => now(),
            'deleted_by' => $request->user()->id_user,
        ]);

        return back()->with('success', 'Pelanggan berhasil dihapus.');
    }
}
