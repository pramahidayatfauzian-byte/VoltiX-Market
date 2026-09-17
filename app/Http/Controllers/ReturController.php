<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\Retur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReturController extends Controller
{
    private function isDeveloper(Request $request): bool
    {
        return ($request->user()->role?->nama_role === 'developer');
    }

    private function authorizeManage(Request $request): void
    {
        $role = $request->user()->role?->nama_role;
        if (! in_array($role, ['developer', 'super admin', 'admin'], true)) {
            abort(403, 'Anda tidak memiliki akses ke retur.');
        }
    }

    /** GET /retur — halaman */
    public function index(Request $request)
    {
        $this->authorizeManage($request);
        $user = $request->user()->loadMissing(['sekolah', 'role']);

        return Inertia::render('Retur', [
            'sekolah' => $user->sekolah ? [
                'id_sekolah' => $user->sekolah->id_sekolah,
                'nama_sekolah' => $user->sekolah->nama_sekolah,
            ] : null,
            'is_super_admin' => $this->isDeveloper($request),
        ]);
    }

    /** Jumlah yang sudah diretur per (tipe, referensi, barang). */
    private function sudahDiretur(string $tipe, int $referensi, int $barang): int
    {
        return (int) Retur::where('tipe', $tipe)
            ->where('id_referensi', $referensi)
            ->where('id_barang', $barang)
            ->sum('jumlah');
    }

    /** GET /retur/penjualan-cari?search= — cari faktur jual + sisa bisa diretur */
    public function cariPenjualan(Request $request)
    {
        $this->authorizeManage($request);
        $search = trim((string) $request->query('search', ''));

        $rows = Penjualan::valid()
            ->with('detail.barang:id_barang,nama')
            ->tenant($request->user()->id_sekolah, $this->isDeveloper($request))
            ->when($search !== '', fn ($q) => $q->where('id_penjualan', 'like', "%{$search}%"))
            ->orderByDesc('id_penjualan')
            ->limit(10)
            ->get()
            ->map(fn ($p) => [
                'id_penjualan' => $p->id_penjualan,
                'tanggal' => $p->tanggal_penjualan?->format('d/m/Y H:i'),
                'items' => $p->detail->map(fn ($d) => [
                    'id_barang' => $d->id_barang,
                    'nama' => $d->barang?->nama,
                    'qty_beli' => (int) $d->jumlah_barang,
                    'harga' => (float) $d->harga_jual,
                    'sudah_retur' => $this->sudahDiretur('penjualan', $p->id_penjualan, $d->id_barang),
                ])->map(fn ($i) => $i + ['sisa' => max(0, $i['qty_beli'] - $i['sudah_retur'])])->values(),
            ]);

        return response()->json(['data' => $rows]);
    }

    /** GET /retur/pembelian-cari?search= — hanya faktur SELESAI (stok sudah masuk) */
    public function cariPembelian(Request $request)
    {
        $this->authorizeManage($request);
        $search = trim((string) $request->query('search', ''));

        $rows = Pembelian::valid()
            ->with('detail.barang:id_barang,nama')
            ->tenant($request->user()->id_sekolah, $this->isDeveloper($request))
            ->where('status_pembelian', 'selesai')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('nomor_faktur', 'like', "%{$search}%")
                        ->orWhere('id_pembelian', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id_pembelian')
            ->limit(10)
            ->get()
            ->map(fn ($p) => [
                'id_pembelian' => $p->id_pembelian,
                'nomor_faktur' => $p->nomor_faktur,
                'tanggal' => $p->tanggal_faktur?->format('d/m/Y H:i'),
                'items' => $p->detail->map(fn ($d) => [
                    'id_barang' => $d->id_barang,
                    'nama' => $d->barang?->nama,
                    'qty_beli' => (int) $d->jumlah,
                    'harga' => (float) $d->harga_beli,
                    'sudah_retur' => $this->sudahDiretur('pembelian', $p->id_pembelian, $d->id_barang),
                ])->map(fn ($i) => $i + ['sisa' => max(0, $i['qty_beli'] - $i['sudah_retur'])])->values(),
            ]);

        return response()->json(['data' => $rows]);
    }

    /** POST /retur/penjualan — barang kembali, stok BERTAMBAH */
    public function storePenjualan(Request $request)
    {
        $this->authorizeManage($request);

        $v = $request->validate([
            'id_penjualan' => ['required', 'integer', 'exists:tb_penjualan,id_penjualan'],
            'alasan' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.id_barang' => ['required', 'integer', 'exists:tb_barang,id_barang'],
            'items.*.jumlah' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        $user = $request->user();
        $isDeveloper = $this->isDeveloper($request);

        try {
            DB::transaction(function () use ($v, $user, $isDeveloper) {
                $jual = Penjualan::valid()
                    ->tenant($user->id_sekolah, $isDeveloper)
                    ->lockForUpdate()
                    ->findOrFail($v['id_penjualan']);

                foreach ($v['items'] as $item) {
                    $detail = $jual->detail()->where('id_barang', $item['id_barang'])->first();
                    if (! $detail) {
                        abort(422, 'Barang tidak ada di faktur ini.');
                    }
                    $sisa = (int) $detail->jumlah_barang
                        - $this->sudahDiretur('penjualan', $jual->id_penjualan, $item['id_barang']);
                    if ($item['jumlah'] > $sisa) {
                        abort(422, "Retur melebihi sisa ({$sisa}).");
                    }

                    $barang = Barang::valid()->lockForUpdate()->findOrFail($item['id_barang']);
                    $harga = (float) $detail->harga_jual;

                    Retur::create([
                        'id_sekolah' => $jual->id_sekolah,
                        'tipe' => 'penjualan',
                        'id_referensi' => $jual->id_penjualan,
                        'id_barang' => $item['id_barang'],
                        'jumlah' => $item['jumlah'],
                        'harga' => $harga,
                        'subtotal' => round($item['jumlah'] * $harga, 2),
                        'alasan' => $v['alasan'] ?? null,
                        'tanggal_retur' => now(),
                        'id_user' => $user->id_user,
                        'created_by' => $user->id_user,
                    ]);

                    $barang->increment('stok', (int) $item['jumlah']);
                }
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return back()->withErrors(['retur' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['retur' => 'Retur gagal disimpan.']);
        }

        return back()->with('success', 'Retur penjualan berhasil, stok bertambah.');
    }

    /** POST /retur/pembelian — barang ke supplier, stok BERKURANG */
    public function storePembelian(Request $request)
    {
        $this->authorizeManage($request);

        $v = $request->validate([
            'id_pembelian' => ['required', 'integer', 'exists:tb_pembelian,id_pembelian'],
            'alasan' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.id_barang' => ['required', 'integer', 'exists:tb_barang,id_barang'],
            'items.*.jumlah' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        $user = $request->user();
        $isDeveloper = $this->isDeveloper($request);

        try {
            DB::transaction(function () use ($v, $user, $isDeveloper) {
                $beli = Pembelian::valid()
                    ->tenant($user->id_sekolah, $isDeveloper)
                    ->where('status_pembelian', 'selesai')
                    ->lockForUpdate()
                    ->findOrFail($v['id_pembelian']);

                foreach ($v['items'] as $item) {
                    $detail = $beli->detail()->where('id_barang', $item['id_barang'])->first();
                    if (! $detail) {
                        abort(422, 'Barang tidak ada di faktur ini.');
                    }
                    $sisa = (int) $detail->jumlah
                        - $this->sudahDiretur('pembelian', $beli->id_pembelian, $item['id_barang']);
                    if ($item['jumlah'] > $sisa) {
                        abort(422, "Retur melebihi sisa ({$sisa}).");
                    }

                    $barang = Barang::valid()->lockForUpdate()->findOrFail($item['id_barang']);
                    if ((int) $barang->stok < (int) $item['jumlah']) {
                        abort(422, "Stok {$barang->nama} tidak cukup untuk diretur (sisa {$barang->stok}).");
                    }
                    $harga = (float) $detail->harga_beli;

                    Retur::create([
                        'id_sekolah' => $beli->id_sekolah,
                        'tipe' => 'pembelian',
                        'id_referensi' => $beli->id_pembelian,
                        'id_barang' => $item['id_barang'],
                        'jumlah' => $item['jumlah'],
                        'harga' => $harga,
                        'subtotal' => round($item['jumlah'] * $harga, 2),
                        'alasan' => $v['alasan'] ?? null,
                        'tanggal_retur' => now(),
                        'id_user' => $user->id_user,
                        'created_by' => $user->id_user,
                    ]);

                    $barang->decrement('stok', (int) $item['jumlah']);
                }
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return back()->withErrors(['retur' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['retur' => 'Retur gagal disimpan.']);
        }

        return back()->with('success', 'Retur pembelian berhasil, stok berkurang.');
    }

    /** GET /retur/data?tipe= — riwayat retur */
    public function data(Request $request)
    {
        $this->authorizeManage($request);

        $query = Retur::with('barang:id_barang,nama')
            ->tenant($request->user()->id_sekolah, $this->isDeveloper($request))
            ->when($request->filled('tipe'), fn ($q) => $q->where('tipe', $request->query('tipe')))
            ->orderByDesc('tanggal_retur')
            ->orderByDesc('id_retur');

        return response()->json($query->paginate(10)->withQueryString());
    }
}
