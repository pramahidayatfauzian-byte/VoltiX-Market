<?php

namespace App\Http\Controllers;

use App\Models\Penjualan;
use App\Models\Sekolah;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanController extends Controller
{
    private function isDeveloper(Request $request): bool
    {
        return ($request->user()->role?->nama_role === 'developer');
    }

    /** Kasir tidak boleh membuka laporan. */
    private function authorizeView(Request $request): void
    {
        $role = $request->user()->role?->nama_role;
        if (! in_array($role, ['developer', 'super admin', 'admin'], true)) {
            abort(403, 'Anda tidak memiliki akses ke laporan.');
        }
    }

    private function filteredQuery(Request $request)
    {
        $user = $request->user();
        $isDeveloper = $this->isDeveloper($request);

        $sekolahId = $isDeveloper
            ? ($request->query('id_sekolah') ?: null)
            : $user->id_sekolah;

        return Penjualan::valid()
            ->with([
                'kasir:id_user,username,nama_lengkap',
                'sekolah:id_sekolah,nama_sekolah',
            ])
            ->when($sekolahId, fn ($q) => $q->where('tb_penjualan.id_sekolah', $sekolahId))
            ->when($request->filled('tgl_mulai'), fn ($q) => $q->whereDate('tanggal_penjualan', '>=', $request->query('tgl_mulai')))
            ->when($request->filled('tgl_akhir'), fn ($q) => $q->whereDate('tanggal_penjualan', '<=', $request->query('tgl_akhir')))
            ->when($request->filled('status'), fn ($q) => $q->where('status_pembayaran', $request->query('status')));
    }

    /** GET /laporan — halaman */
    public function index(Request $request)
    {
        $this->authorizeView($request);
        $user = $request->user()->loadMissing(['sekolah', 'role']);

        return Inertia::render('Laporan', [
            'sekolah' => $user->sekolah ? [
                'id_sekolah' => $user->sekolah->id_sekolah,
                'nama_sekolah' => $user->sekolah->nama_sekolah,
            ] : null,
            'sekolah_list' => $this->isDeveloper($request)
                ? Sekolah::where('is_active', true)->orderBy('nama_sekolah')->get(['id_sekolah', 'nama_sekolah'])
                : collect(),
            'is_super_admin' => $this->isDeveloper($request),
            'default_mulai' => Carbon::today()->subDays(29)->format('Y-m-d'),
            'default_akhir' => Carbon::today()->format('Y-m-d'),
        ]);
    }

    /** GET /laporan/data — JSON ringkasan + tabel paginated */
    public function data(Request $request)
    {
        $this->authorizeView($request);

        $base = $this->filteredQuery($request);

        $ringkasan = [
            'omzet' => round((float) (clone $base)->sum('total_faktur'), 2),
            'transaksi' => (int) (clone $base)->count(),
            'rata_rata' => 0,
        ];
        $ringkasan['rata_rata'] = $ringkasan['transaksi'] > 0
            ? round($ringkasan['omzet'] / $ringkasan['transaksi'], 2)
            : 0;

        $tabel = (clone $base)
            ->orderByDesc('tanggal_penjualan')
            ->orderByDesc('id_penjualan')
            ->paginate(10)
            ->withQueryString();

        return response()->json([
            'ringkasan' => $ringkasan,
            'tabel' => $tabel,
        ]);
    }

    /** GET /laporan/export — unduh CSV */
    public function export(Request $request): StreamedResponse
    {
        $this->authorizeView($request);

        $rows = $this->filteredQuery($request)
            ->orderBy('tanggal_penjualan')
            ->orderBy('id_penjualan')
            ->cursor();

        $filename = 'laporan-penjualan-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Tanggal', 'Sekolah', 'Kasir', 'Total Faktur', 'Total Bayar', 'Kembalian', 'Status', 'Jenis', 'Cara Bayar']);
            foreach ($rows as $p) {
                fputcsv($out, [
                    $p->id_penjualan,
                    $p->tanggal_penjualan?->format('Y-m-d H:i:s'),
                    $p->sekolah?->nama_sekolah,
                    $p->kasir?->nama_lengkap ?? $p->kasir?->username,
                    $p->total_faktur,
                    $p->total_bayar,
                    $p->kembalian,
                    $p->status_pembayaran,
                    $p->jenis_transaksi,
                    $p->cara_bayar,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
