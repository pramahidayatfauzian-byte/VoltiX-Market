<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\DetailPembelian;
use App\Models\Pembelian;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PembelianController extends Controller
{
    private function isDeveloper(Request $request): bool
    {
        return ($request->user()->role?->nama_role === 'developer');
    }

    /** Kasir dilarang. */
    private function authorizeManage(Request $request): void
    {
        $role = $request->user()->role?->nama_role;
        if (! in_array($role, ['developer', 'super admin', 'admin'], true)) {
            abort(403, 'Anda tidak memiliki akses ke pembelian & supplier.');
        }
    }

    /** GET /pembelian — halaman + dropdown */
    public function index(Request $request)
    {
        $this->authorizeManage($request);
        $user = $request->user()->loadMissing(['sekolah', 'role']);
        $isDeveloper = $this->isDeveloper($request);

        $supplier = Supplier::valid()->tenant($user->id_sekolah, $isDeveloper)
            ->orderBy('nama')->get(['id_supplier', 'nama']);
        $barang = Barang::valid()->tenant($user->id_sekolah, $isDeveloper)
            ->where('is_active', true)
            ->orderBy('nama')->get(['id_barang', 'barcode', 'nama', 'satuan', 'harga_beli', 'stok']);
        $sekolahList = $isDeveloper
            ? \App\Models\Sekolah::where('is_active', true)->orderBy('nama_sekolah')->get(['id_sekolah', 'nama_sekolah'])
            : collect();

        return Inertia::render('Pembelian', [
            'sekolah' => $user->sekolah ? [
                'id_sekolah' => $user->sekolah->id_sekolah,
                'nama_sekolah' => $user->sekolah->nama_sekolah,
            ] : null,
            'supplier_list' => $supplier,
            'barang_list' => $barang,
            'sekolah_list' => $sekolahList,
            'is_super_admin' => $isDeveloper,
        ]);
    }

    /** GET /pembelian/data — JSON + filter */
    public function data(Request $request)
    {
        $this->authorizeManage($request);

        $query = Pembelian::valid()
            ->with(['supplier:id_supplier,nama', 'user:id_user,nama_lengkap,username'])
            ->tenant($request->user()->id_sekolah, $this->isDeveloper($request))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->query('search');
                $q->where('nomor_faktur', 'like', "%{$s}%");
            })
            ->when($request->filled('id_supplier'), fn ($q) => $q->where('id_supplier', $request->query('id_supplier')))
            ->when($request->filled('tgl_mulai'), fn ($q) => $q->whereDate('tanggal_faktur', '>=', $request->query('tgl_mulai')))
            ->when($request->filled('tgl_akhir'), fn ($q) => $q->whereDate('tanggal_faktur', '<=', $request->query('tgl_akhir')))
            ->when($request->filled('status'), fn ($q) => $q->where('status_pembelian', $request->query('status')))
            ->orderByDesc('tanggal_faktur')
            ->orderByDesc('id_pembelian');

        return response()->json($query->paginate(10)->withQueryString());
    }

    /** GET /pembelian/{id} — detail + items JSON */
    public function show(Request $request, int $id)
    {
        $this->authorizeManage($request);

        $pembelian = Pembelian::valid()
            ->with([
                'supplier:id_supplier,nama,no_telepon',
                'user:id_user,nama_lengkap,username',
                'detail.barang:id_barang,barcode,nama',
            ])
            ->tenant($request->user()->id_sekolah, $this->isDeveloper($request))
            ->findOrFail($id);

        return response()->json(['data' => $pembelian]);
    }

    private function validateFaktur(Request $request): array
    {
        return $request->validate([
            'id_supplier' => ['required', 'integer', 'exists:tb_supplier,id_supplier'],
            'nomor_faktur' => ['required', 'string', 'max:50'],
            'tanggal_faktur' => ['required', 'date'],
            'status_pembelian' => ['required', 'in:draft,selesai'],
            'jenis_transaksi' => ['required', 'in:tunai,kredit'],
            'cara_bayar' => ['required', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id_barang' => ['required', 'integer', 'exists:tb_barang,id_barang'],
            'items.*.jumlah' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.harga_beli' => ['required', 'numeric', 'min:0', 'max:100000000000'],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeManage($request);
        $v = $this->validateFaktur($request);
        $user = $request->user();
        $isDeveloper = $this->isDeveloper($request);

        try {
            DB::transaction(function () use ($v, $user, $isDeveloper) {
                // Supplier harus milik tenant
                $sup = Supplier::valid()->findOrFail($v['id_supplier']);
                if (! $isDeveloper && (int) $sup->id_sekolah !== (int) $user->id_sekolah) {
                    abort(422, 'Supplier bukan milik sekolah ini.');
                }

                $total = 0;
                $rows = [];
                foreach ($v['items'] as $item) {
                    $barang = Barang::valid()->lockForUpdate()->findOrFail($item['id_barang']);
                    if (! $isDeveloper && (int) $barang->id_sekolah !== (int) $user->id_sekolah) {
                        abort(422, "Produk {$barang->nama} bukan milik sekolah ini.");
                    }
                    $sub = round($item['jumlah'] * $item['harga_beli'], 2);
                    $total += $sub;
                    $rows[] = [
                        'barang' => $barang,
                        'jumlah' => (int) $item['jumlah'],
                        'harga_beli' => (float) $item['harga_beli'],
                        'subtotal' => $sub,
                    ];
                }

                /** @var Pembelian $pembelian */
                $pembelian = Pembelian::create([
                    'id_sekolah' => $user->id_sekolah,
                    'id_supplier' => $v['id_supplier'],
                    'id_user' => $user->id_user,
                    'nomor_faktur' => $v['nomor_faktur'],
                    'tanggal_faktur' => $v['tanggal_faktur'],
                    'total_bayar' => round($total, 2),
                    'status_pembelian' => $v['status_pembelian'],
                    'jenis_transaksi' => $v['jenis_transaksi'],
                    'cara_bayar' => $v['cara_bayar'],
                    'note' => $v['note'] ?? null,
                    'created_by' => $user->id_user,
                    'is_delete' => 0,
                ]);

                foreach ($rows as $row) {
                    $pembelian->detail()->create([
                        'id_barang' => $row['barang']->id_barang,
                        'satuan' => $row['barang']->satuan,
                        'jumlah' => $row['jumlah'],
                        'harga_beli' => $row['harga_beli'],
                        'subtotal' => $row['subtotal'],
                    ]);

                    // Stok bertambah hanya saat selesai
                    if ($v['status_pembelian'] === 'selesai') {
                        $row['barang']->increment('stok', $row['jumlah']);
                    }
                }
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return back()->withErrors(['faktur' => $e->getMessage()])->withInput();
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['faktur' => 'Pembelian gagal disimpan. Silakan coba lagi.'])->withInput();
        }

        return back()->with('success', 'Pembelian berhasil disimpan.');
    }

    /** POST /pembelian/{id}/selesaikan — draft -> selesai + tambah stok */
    public function selesaikan(Request $request, int $id)
    {
        $this->authorizeManage($request);

        try {
            DB::transaction(function () use ($request, $id) {
                $pembelian = Pembelian::valid()
                    ->tenant($request->user()->id_sekolah, $this->isDeveloper($request))
                    ->lockForUpdate()
                    ->findOrFail($id);

                if ($pembelian->status_pembelian !== 'draft') {
                    abort(422, 'Hanya faktur draft yang bisa diselesaikan.');
                }

                foreach ($pembelian->detail as $d) {
                    $barang = Barang::valid()->lockForUpdate()->findOrFail($d->id_barang);
                    $barang->increment('stok', (int) $d->jumlah);
                }

                $pembelian->update(['status_pembelian' => 'selesai']);
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return back()->withErrors(['faktur' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['faktur' => 'Gagal menyelesaikan pembelian.']);
        }

        return back()->with('success', 'Pembelian diselesaikan, stok bertambah.');
    }

    public function destroy(Request $request, int $id)
    {
        $this->authorizeManage($request);

        $pembelian = Pembelian::valid()
            ->tenant($request->user()->id_sekolah, $this->isDeveloper($request))
            ->findOrFail($id);

        if ($pembelian->status_pembelian !== 'draft') {
            return back()->withErrors(['faktur' => 'Faktur selesai tidak bisa dihapus (stok sudah masuk).']);
        }

        $pembelian->update([
            'is_delete' => 1,
            'deleted_at' => now(),
            'deleted_by' => $request->user()->id_user,
        ]);
        DetailPembelian::where('id_pembelian', $id)->delete();

        return back()->with('success', 'Pembelian draft berhasil dihapus.');
    }
}
