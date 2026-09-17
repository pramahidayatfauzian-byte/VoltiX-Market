<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Penjualan;
use App\Models\Retur;
use App\Support\Tenant;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    /** GET /notifikasi — JSON untuk bell di header */
    public function index(Request $request)
    {
        [$sekolahId, $semua] = Tenant::resolve($request);

        $stokMenipis = Barang::valid()
            ->tenant($sekolahId, $semua)
            ->where('is_active', true)
            ->where('stok', '<=', Barang::BATAS_MENIPIS)
            ->orderBy('stok')->orderBy('nama')
            ->limit(5)
            ->get(['id_barang', 'nama', 'stok'])
            ->map(fn ($b) => [
                'teks' => "{$b->nama} — sisa {$b->stok}",
                'href' => '/produk',
                'warna' => (int) $b->stok <= 0 ? 'red' : 'amber',
            ]);

        $piutang = Penjualan::valid()
            ->tenant($sekolahId, $semua)
            ->where('status_pembayaran', 'belum bayar')
            ->count();

        $returBaru = Retur::tenant($sekolahId, $semua)
            ->where('tanggal_retur', '>=', now()->subDays(7))
            ->count();

        $items = collect();

        foreach ($stokMenipis as $s) {
            $items->push([
                'judul' => 'Stok menipis',
                'teks' => $s['teks'],
                'href' => $s['href'],
                'ikon' => 'TriangleAlert',
                'warna' => $s['warna'],
            ]);
        }

        if ($piutang > 0) {
            $items->push([
                'judul' => 'Piutang',
                'teks' => "{$piutang} faktur belum lunas",
                'href' => '/piutang',
                'ikon' => 'HandCoins',
                'warna' => 'amber',
            ]);
        }

        if ($returBaru > 0) {
            $items->push([
                'judul' => 'Retur baru',
                'teks' => "{$returBaru} retur dalam 7 hari",
                'href' => '/retur',
                'ikon' => 'RotateCcw',
                'warna' => 'sky',
            ]);
        }

        if ($items->isEmpty()) {
            $items->push([
                'judul' => 'Semua aman',
                'teks' => 'Tidak ada peringatan saat ini.',
                'href' => null,
                'ikon' => 'CheckCircle',
                'warna' => 'teal',
            ]);
        }

        return response()->json([
            'total' => $items->count(),
            'items' => $items->values(),
        ]);
    }
}
