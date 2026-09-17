<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\KelompokKategori;
use App\Models\Supplier;
use App\Support\KatalogProduk;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProdukController extends Controller
{
    private function isDeveloper(Request $request): bool
    {
        return ($request->user()->role?->nama_role === 'developer');
    }

    /** Semua role boleh melihat produk; kasir hanya boleh melihat (lihat authorizeManage). */
    private function authorizeAkses(Request $request): void
    {
        // Developer global, super admin/admin/kasir per-sekolah — semua boleh lihat.
    }

    /** Kasir hanya boleh melihat produk. */
    private function authorizeManage(Request $request): void
    {
        $this->authorizeAkses($request);
        if (($request->user()->role?->nama_role ?? '') === 'kasir') {
            abort(403, 'Kasir hanya boleh melihat produk.');
        }
    }

    /** GET /produk — halaman + dropdown data */
    public function index(Request $request)
    {
        $this->authorizeAkses($request);
        $user = $request->user()->loadMissing(['sekolah', 'role']);
        $isDeveloper = $this->isDeveloper($request);

        $kategori = Kategori::valid()->tenant($user->id_sekolah, $isDeveloper)
            ->orderBy('nama')->get(['id_kategori', 'nama', 'id_kelompok']);
        $kelompok = KelompokKategori::tenant($user->id_sekolah, $isDeveloper)
            ->orderBy('nama_kelompok')->get(['id', 'id_sekolah', 'nama_kelompok']);
        $supplier = Supplier::valid()->tenant($user->id_sekolah, $isDeveloper)
            ->orderBy('nama')->get(['id_supplier', 'nama']);
        $sekolahList = $isDeveloper
            ? \App\Models\Sekolah::where('is_active', true)->orderBy('nama_sekolah')->get(['id_sekolah', 'nama_sekolah'])
            : collect();

        return Inertia::render('Produk', [
            'sekolah' => $user->sekolah ? [
                'id_sekolah' => $user->sekolah->id_sekolah,
                'nama_sekolah' => $user->sekolah->nama_sekolah,
            ] : null,
            'kategori_list' => $kategori,
            'kelompok_list' => $kelompok,
            'supplier_list' => $supplier,
            'sekolah_list' => $sekolahList,
            'is_super_admin' => $isDeveloper,
            'can_manage' => ($user->role?->nama_role ?? '') !== 'kasir',
        ]);
    }

    /** GET /produk/data — JSON paginated + search + filter */
    public function data(Request $request)
    {
        $this->authorizeAkses($request);
        $user = $request->user();
        $isDeveloper = $this->isDeveloper($request);

        $query = Barang::valid()
            ->with([
                'kategori:id_kategori,nama',
                'kelompokKategori:id,nama_kelompok',
                'supplier:id_supplier,nama',
                'sekolah:id_sekolah,nama_sekolah',
            ])
            ->tenant($user->id_sekolah, $isDeveloper)
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = trim((string) $request->query('search'));
                $sRaw = preg_replace('/\s+/', '', $s);
                $q->where(function ($w) use ($s, $sRaw) {
                    $w->where('nama', 'like', "%{$s}%")
                        ->orWhere('barcode', 'like', "%{$s}%")
                        ->orWhere('barcode', 'like', "%{$sRaw}%");
                });
            })
            ->when($request->filled('id_kategori'), fn ($q) => $q->where('id_kategori', $request->query('id_kategori')))
            ->when($request->query('status') === 'aktif', fn ($q) => $q->where('is_active', true))
            ->when($request->query('status') === 'nonaktif', fn ($q) => $q->where('is_active', false))
            ->orderBy('nama');

        return response()->json($query->paginate(10)->withQueryString());
    }

    /** GET /produk/lookup-barcode?barcode= — kenali barcode untuk prefill form tambah.
     * Mengembalikan produk lokal bila sudah terdaftar, atau kandidat katalog
     * (master lokal / OpenFoodFacts / kosong) agar form terisi otomatis:
     * nama produk + kategorinya. */
    public function lookupBarcode(Request $request)
    {
        $this->authorizeAkses($request);
        $user = $request->user();
        $isDeveloper = $this->isDeveloper($request);
        $barcode = preg_replace('/\s+/', '', trim((string) $request->query('barcode', '')));
        if ($barcode === '') {
            return response()->json(['produk' => null, 'kandidat' => null]);
        }

        $produk = Barang::valid()
            ->with([
                'kategori:id_kategori,nama',
                'kelompokKategori:id,nama_kelompok',
                'supplier:id_supplier,nama',
                'sekolah:id_sekolah,nama_sekolah',
            ])
            ->tenant($user->id_sekolah, $isDeveloper)
            ->where(function ($w) use ($barcode) {
                $w->where('barcode', $barcode)
                    ->orWhere('barcode', ltrim($barcode, '0'));
            })
            ->first();
        if ($produk) {
            return response()->json(['produk' => $produk, 'kandidat' => null]);
        }

        $kandidat = preg_match('/^\d{6,}$/', $barcode)
            ? KatalogProduk::kandidat($barcode)
            : null;

        return response()->json(['produk' => null, 'kandidat' => $kandidat]);
    }

    /** GET /produk/{id}/riwayat — kartu stok (masuk dari pembelian selesai, keluar dari penjualan) */
    public function riwayat(Request $request, int $id)
    {
        $this->authorizeAkses($request);
        $barang = Barang::valid()
            ->tenant($request->user()->id_sekolah, $this->isDeveloper($request))
            ->findOrFail($id);

        $masuk = \App\Models\DetailPembelian::query()
            ->join('tb_pembelian as p', 'p.id_pembelian', '=', 'tb_detail_pembelian.id_pembelian')
            ->where('tb_detail_pembelian.id_barang', $id)
            ->where('p.status_pembelian', 'selesai')
            ->where(function ($q) {
                $q->whereNull('p.is_delete')->orWhere('p.is_delete', 0);
            })
            ->orderBy('p.tanggal_faktur')
            ->orderBy('p.id_pembelian')
            ->get([
                'p.tanggal_faktur as tanggal',
                'p.nomor_faktur as referensi',
                'tb_detail_pembelian.jumlah as jumlah',
                'tb_detail_pembelian.harga_beli as harga',
            ])
            ->map(fn ($r) => [
                'tanggal' => $r->tanggal,
                'keterangan' => 'Pembelian '.($r->referensi ?? '#'),
                'masuk' => (int) $r->jumlah,
                'keluar' => 0,
                'harga' => (float) $r->harga,
            ]);

        $keluar = \App\Models\DetailPenjualan::query()
            ->join('tb_penjualan as p', 'p.id_penjualan', '=', 'tb_detail_penjualan.id_penjualan')
            ->where('tb_detail_penjualan.id_barang', $id)
            ->where(function ($q) {
                $q->whereNull('p.is_delete')->orWhere('p.is_delete', 0);
            })
            ->whereNull('p.deleted_at')
            ->orderBy('p.tanggal_penjualan')
            ->orderBy('p.id_penjualan')
            ->get([
                'p.tanggal_penjualan as tanggal',
                'p.id_penjualan as referensi',
                'tb_detail_penjualan.jumlah_barang as jumlah',
                'tb_detail_penjualan.harga_jual as harga',
            ])
            ->map(fn ($r) => [
                'tanggal' => $r->tanggal,
                'keterangan' => 'Penjualan #'.$r->referensi,
                'masuk' => 0,
                'keluar' => (int) $r->jumlah,
                'harga' => (float) $r->harga,
            ]);

        $gerakan = $masuk->concat($keluar)
            ->sortBy([['tanggal', 'asc']])
            ->values();

        $totalMasuk = $gerakan->sum('masuk');
        $totalKeluar = $gerakan->sum('keluar');
        $saldo = (int) $barang->stok - $totalMasuk + $totalKeluar;

        $kartu = $gerakan->map(function ($g) use (&$saldo) {
            $saldo = $saldo + $g['masuk'] - $g['keluar'];

            return $g + ['sisa' => $saldo];
        });

        return response()->json([
            'barang' => [
                'id_barang' => $barang->id_barang,
                'nama' => $barang->nama,
                'stok' => (int) $barang->stok,
                'satuan' => $barang->satuan,
            ],
            'total_masuk' => $totalMasuk,
            'total_keluar' => $totalKeluar,
            'kartu' => $kartu->values(),
        ]);
    }

    /** GET /produk/{id} — detail JSON */
    public function show(Request $request, int $id)
    {
        $this->authorizeAkses($request);
        $barang = Barang::valid()
            ->with(['kategori:id_kategori,nama', 'kelompokKategori:id,nama_kelompok', 'supplier:id_supplier,nama', 'sekolah:id_sekolah,nama_sekolah'])
            ->tenant($request->user()->id_sekolah, $this->isDeveloper($request))
            ->findOrFail($id);

        return response()->json(['data' => $barang]);
    }

    private function validateBarang(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'id_sekolah' => ['nullable', 'integer', 'exists:tb_sekolah,id_sekolah'],
            'barcode' => ['nullable', 'string', 'max:50'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'nama' => ['required', 'string', 'max:150'],
            'id_kategori' => ['nullable', 'integer', 'exists:tb_kategori,id_kategori'],
            'id_kelompok_kategori' => ['nullable', 'integer', 'exists:tb_kelompok_kategori,id'],
            'id_supplier' => ['nullable', 'integer', 'exists:tb_supplier,id_supplier'],
            'satuan' => ['nullable', 'string', 'max:20'],
            'harga_beli' => ['nullable', 'numeric', 'min:0', 'max:100000000000'],
            'harga_jual' => ['required', 'numeric', 'min:0', 'max:100000000000'],
            'stok' => ['required', 'integer', 'min:0', 'max:100000000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    /** Cek FK dropdown milik tenant (kecuali super admin) */
    private function assertTenantFk(Request $request, array $v): ?string
    {
        if ($this->isDeveloper($request)) {
            return null;
        }
        $sid = $request->user()->id_sekolah;

        if (! empty($v['id_kategori'])) {
            $ok = Kategori::valid()->tenant($sid, false)->where('id_kategori', $v['id_kategori'])->exists();
            if (! $ok) {
                return 'Kategori tidak valid untuk tenant ini.';
            }
        }
        if (! empty($v['id_kelompok_kategori'])) {
            $ok = KelompokKategori::tenant($sid, false)->where('id', $v['id_kelompok_kategori'])->exists();
            if (! $ok) {
                return 'Kelompok kategori tidak valid untuk tenant ini.';
            }
        }
        if (! empty($v['id_supplier'])) {
            $ok = Supplier::valid()->tenant($sid, false)->where('id_supplier', $v['id_supplier'])->exists();
            if (! $ok) {
                return 'Supplier tidak valid untuk tenant ini.';
            }
        }

        return null;
    }

    public function store(Request $request)
    {
        $this->authorizeManage($request);
        $v = $this->validateBarang($request);
        if ($err = $this->assertTenantFk($request, $v)) {
            return back()->withErrors(['id_kategori' => $err]);
        }

        $foto = $request->hasFile('foto')
            ? $request->file('foto')->store('barang', 'public')
            : null;
        unset($v['foto']);

        Barang::create($v + [
            'id_sekolah' => $this->isDeveloper($request)
                ? ($v['id_sekolah'] ?? $request->user()->id_sekolah)
                : $request->user()->id_sekolah,
            'is_active' => filter_var($v['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'foto' => $foto,
            'created_by' => $request->user()->id_user,
            'is_delete' => 0,
        ]);

        return back()->with('success', 'Produk berhasil ditambahkan.');
    }

    public function update(Request $request, int $id)
    {
        $this->authorizeManage($request);
        $barang = Barang::valid()
            ->tenant($request->user()->id_sekolah, $this->isDeveloper($request))
            ->findOrFail($id);

        $v = $this->validateBarang($request, $id);
        if ($err = $this->assertTenantFk($request, $v)) {
            return back()->withErrors(['id_kategori' => $err]);
        }

        if (! $this->isDeveloper($request)) {
            unset($v['id_sekolah']);
        }
        unset($v['foto']);

        if ($request->hasFile('foto')) {
            if ($barang->foto) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($barang->foto);
            }
            $barang->foto = $request->file('foto')->store('barang', 'public');
        }
        if (array_key_exists('is_active', $v)) {
            $v['is_active'] = filter_var($v['is_active'], FILTER_VALIDATE_BOOLEAN);
        }
        $barang->update($v + ['updated_by' => $request->user()->id_user]);

        return back()->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $this->authorizeManage($request);
        $barang = Barang::valid()
            ->tenant($request->user()->id_sekolah, $this->isDeveloper($request))
            ->findOrFail($id);

        // Jangan hapus produk yang sudah dipakai transaksi (FK restrict)
        $dipakai = \App\Models\DetailPenjualan::where('id_barang', $id)->exists()
            || \App\Models\DetailPembelian::where('id_barang', $id)->exists();
        if ($dipakai) {
            return back()->withErrors(['produk' => 'Produk sudah dipakai transaksi, tidak bisa dihapus. Nonaktifkan saja.']);
        }

        if ($barang->foto) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($barang->foto);
        }
        $barang->update([
            'is_delete' => 1,
            'deleted_at' => now(),
            'deleted_by' => $request->user()->id_user,
        ]);

        return back()->with('success', 'Produk berhasil dihapus.');
    }

    public function toggle(Request $request, int $id)
    {
        $this->authorizeManage($request);
        $barang = Barang::valid()
            ->tenant($request->user()->id_sekolah, $this->isDeveloper($request))
            ->findOrFail($id);

        $barang->update(['is_active' => ! $barang->is_active]);

        return back()->with('success', $barang->is_active ? 'Produk diaktifkan.' : 'Produk dinonaktifkan.');
    }
}
