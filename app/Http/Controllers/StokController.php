<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\DetailPembelian;
use App\Models\DetailPenjualan;
use App\Models\Kategori;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StokController extends Controller
{
    private function isDeveloper(Request $request): bool
    {
        return ($request->user()->role?->nama_role === 'developer');
    }

    /** Kasir tidak punya halaman stok (stok terlihat di kasir saat jualan). */
    private function authorizeAkses(Request $request): void
    {
        if (($request->user()->role?->nama_role ?? '') === 'kasir') {
            abort(403, 'Kasir tidak dapat mengakses halaman stok.');
        }
        // Developer global, super admin/admin per-sekolah — semua boleh lihat.
    }

    /** GET /stok — halaman */
    public function index(Request $request)
    {
        $this->authorizeAkses($request);
        $user = $request->user()->loadMissing(['sekolah', 'role']);
        $isDeveloper = $this->isDeveloper($request);
        [$sekolahId, $semua] = [null, true];
        if (! $isDeveloper) {
            $sekolahId = $user->id_sekolah;
            $semua = false;
        }

        $kategori = Kategori::valid()->tenant($sekolahId, $semua)
            ->orderBy('nama')->get(['id_kategori', 'nama']);

        return Inertia::render('Stok', [
            'sekolah' => $user->sekolah ? [
                'id_sekolah' => $user->sekolah->id_sekolah,
                'nama_sekolah' => $user->sekolah->nama_sekolah,
            ] : null,
            'kategori_list' => $kategori,
            'batas_menipis' => Barang::BATAS_MENIPIS,
            'is_super_admin' => $isDeveloper,
        ]);
    }

    /** GET /stok/data — JSON paginated + ringkasan + arus masuk/keluar */
    public function data(Request $request)
    {
        $this->authorizeAkses($request);
        $user = $request->user();
        $isDeveloper = $this->isDeveloper($request);
        $sekolahId = $isDeveloper ? null : $user->id_sekolah;
        $semua = $isDeveloper;
        $batas = Barang::BATAS_MENIPIS;

        $base = Barang::valid()->tenant($sekolahId, $semua)->where('is_active', true);

        $ringkasan = [
            'total_produk' => (clone $base)->count(),
            'menipis' => (clone $base)->where('stok', '>', 0)->where('stok', '<=', $batas)->count(),
            'habis' => (clone $base)->where('stok', '<=', 0)->count(),
            'nilai_stok' => (clone $base)->sum(\DB::raw('COALESCE(stok, 0) * COALESCE(harga_beli, 0)')),
        ];

        $query = Barang::valid()
            ->with(['kategori:id_kategori,nama'])
            ->tenant($sekolahId, $semua)
            ->where('is_active', true)
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
            ->when($request->query('kondisi') === 'menipis', fn ($q) => $q->where('stok', '>', 0)->where('stok', '<=', $batas))
            ->when($request->query('kondisi') === 'habis', fn ($q) => $q->where('stok', '<=', 0))
            ->orderBy('stok')
            ->orderBy('nama');

        $tabel = $query->paginate(10)->withQueryString();
        $ids = $tabel->getCollection()->pluck('id_barang')->all();

        $masuk = DetailPembelian::query()
            ->join('tb_pembelian as p', 'p.id_pembelian', '=', 'tb_detail_pembelian.id_pembelian')
            ->whereIn('tb_detail_pembelian.id_barang', $ids)
            ->where('p.status_pembelian', 'selesai')
            ->where(function ($q) {
                $q->whereNull('p.is_delete')->orWhere('p.is_delete', 0);
            })
            ->when(! $semua && $sekolahId, fn ($q) => $q->where('p.id_sekolah', $sekolahId))
            ->groupBy('tb_detail_pembelian.id_barang')
            ->selectRaw('tb_detail_pembelian.id_barang as id, SUM(tb_detail_pembelian.jumlah) as total')
            ->pluck('total', 'id');

        $keluar = DetailPenjualan::query()
            ->join('tb_penjualan as p', 'p.id_penjualan', '=', 'tb_detail_penjualan.id_penjualan')
            ->whereIn('tb_detail_penjualan.id_barang', $ids)
            ->where(function ($q) {
                $q->whereNull('p.is_delete')->orWhere('p.is_delete', 0);
            })
            ->when(! $semua && $sekolahId, fn ($q) => $q->where('p.id_sekolah', $sekolahId))
            ->groupBy('tb_detail_penjualan.id_barang')
            ->selectRaw('tb_detail_penjualan.id_barang as id, SUM(tb_detail_penjualan.jumlah_barang) as total')
            ->pluck('total', 'id');

        // Samakan data arus dengan kartu stok produk (masuk = pembelian selesai).
        $tabel->getCollection()->transform(function ($b) use ($masuk, $keluar) {
            $b->total_masuk = (int) ($masuk[$b->id_barang] ?? 0);
            $b->total_keluar = (int) ($keluar[$b->id_barang] ?? 0);

            return $b;
        });

        return response()->json([
            'ringkasan' => $ringkasan,
            'batas_menipis' => $batas,
            'tabel' => $tabel,
        ]);
    }
}
