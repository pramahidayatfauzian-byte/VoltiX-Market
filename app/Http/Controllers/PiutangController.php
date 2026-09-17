<?php

namespace App\Http\Controllers;

use App\Models\Penjualan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PiutangController extends Controller
{
    private function isDeveloper(Request $request): bool
    {
        return ($request->user()->role?->nama_role === 'developer');
    }

    private function authorizeView(Request $request): void
    {
        $role = $request->user()->role?->nama_role;
        if (! in_array($role, ['developer', 'super admin', 'admin'], true)) {
            abort(403, 'Anda tidak memiliki akses ke piutang.');
        }
    }

    /** GET /piutang — halaman */
    public function index(Request $request)
    {
        $this->authorizeView($request);
        $user = $request->user()->loadMissing(['sekolah', 'role']);

        return Inertia::render('Piutang', [
            'sekolah' => $user->sekolah ? [
                'id_sekolah' => $user->sekolah->id_sekolah,
                'nama_sekolah' => $user->sekolah->nama_sekolah,
            ] : null,
            'is_super_admin' => $this->isDeveloper($request),
        ]);
    }

    private function baseQuery(Request $request)
    {
        return Penjualan::valid()
            ->with([
                'kasir:id_user,username,nama_lengkap',
                'sekolah:id_sekolah,nama_sekolah',
            ])
            ->withAggregate('pelanggan as nama_pelanggan', 'nama_pelanggan')
            ->tenant($request->user()->id_sekolah, $this->isDeveloper($request))
            ->where('status_pembayaran', 'belum bayar');
    }

    /** GET /piutang/data — JSON ringkasan + tabel */
    public function data(Request $request)
    {
        $this->authorizeView($request);

        $query = $this->baseQuery($request)
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->query('search');
                $q->where(function ($w) use ($s) {
                    $w->where('id_penjualan', 'like', "%{$s}%")
                        ->orWhereHas('pelanggan', fn ($p) => $p->where('nama_pelanggan', 'like', "%{$s}%"));
                });
            })
            ->orderBy('tanggal_penjualan')
            ->orderBy('id_penjualan');

        $semua = (clone $query)->get(['id_penjualan', 'total_faktur', 'total_bayar']);
        $ringkasan = [
            'faktur' => $semua->count(),
            'total_piutang' => round($semua->sum(fn ($p) => max(0, (float) $p->total_faktur - (float) $p->total_bayar)), 2),
        ];

        $tabel = $query->paginate(10)->withQueryString();
        $tabel->getCollection()->transform(fn ($p) => [
            'id_penjualan' => $p->id_penjualan,
            'tanggal' => $p->tanggal_penjualan?->format('d/m/Y H:i'),
            'pelanggan' => $p->nama_pelanggan,
            'kasir' => $p->kasir?->nama_lengkap ?? $p->kasir?->username,
            'sekolah' => $p->sekolah?->nama_sekolah,
            'total_faktur' => (float) $p->total_faktur,
            'sudah_bayar' => (float) $p->total_bayar,
            'sisa' => round(max(0, (float) $p->total_faktur - (float) $p->total_bayar), 2),
            'cara_bayar' => $p->cara_bayar,
        ]);

        return response()->json(['ringkasan' => $ringkasan, 'tabel' => $tabel]);
    }

    /** POST /piutang/{id}/lunasi — catat pembayaran (bisa cicil) */
    public function lunasi(Request $request, int $id)
    {
        $this->authorizeView($request);

        $v = $request->validate([
            'jumlah' => ['required', 'numeric', 'min:1', 'max:100000000000'],
            'cara_bayar' => ['required', 'string', 'max:50'],
        ]);

        try {
            $hasil = DB::transaction(function () use ($request, $id, $v) {
                $p = Penjualan::valid()
                    ->tenant($request->user()->id_sekolah, $this->isDeveloper($request))
                    ->lockForUpdate()
                    ->findOrFail($id);

                if ($p->status_pembayaran !== 'belum bayar') {
                    abort(422, 'Transaksi ini sudah lunas.');
                }

                $sisa = round((float) $p->total_faktur - (float) $p->total_bayar, 2);
                if ($sisa <= 0) {
                    $p->update(['status_pembayaran' => 'sudah bayar']);

                    return ['lunas' => true, 'kembalian' => 0];
                }

                $bayar = round((float) $v['jumlah'], 2);
                $baru = round((float) $p->total_bayar + $bayar, 2);

                if ($baru >= (float) $p->total_faktur) {
                    $p->update([
                        'total_bayar' => $baru,
                        'kembalian' => round($baru - (float) $p->total_faktur, 2),
                        'status_pembayaran' => 'sudah bayar',
                        'cara_bayar' => $v['cara_bayar'],
                    ]);

                    return ['lunas' => true, 'kembalian' => round($baru - (float) $p->total_faktur, 2)];
                }

                $p->update([
                    'total_bayar' => $baru,
                    'cara_bayar' => $v['cara_bayar'],
                ]);

                return ['lunas' => false, 'sisa' => round((float) $p->total_faktur - $baru, 2)];
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return back()->withErrors(['piutang' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['piutang' => 'Gagal menyimpan pelunasan.']);
        }

        return back()->with(
            'success',
            $hasil['lunas']
                ? 'Piutang lunas.'.($hasil['kembalian'] > 0 ? ' Kembalian Rp '.number_format($hasil['kembalian'], 0, ',', '.') : '')
                : 'Cicilan tercatat. Sisa Rp '.number_format($hasil['sisa'], 0, ',', '.')
        );
    }
}
