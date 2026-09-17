<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AuditController extends Controller
{
    private const PER_PAGE = 15;
    private const PER_SOURCE = 40;

    private function isDeveloper(Request $request): bool
    {
        return ($request->user()->role?->nama_role === 'developer');
    }

    private function authorizeView(Request $request): void
    {
        $role = $request->user()->role?->nama_role;
        if (! in_array($role, ['developer', 'super admin', 'admin'], true)) {
            abort(403, 'Anda tidak memiliki akses ke audit log.');
        }
    }

    /**
     * Sumber jejak audit dari kolom yang SUDAH ADA di db_zian.
     * tenant: kolom sekolah langsung, 'kelompok' (via tb_kelompok_kategori),
     * atau null (global, hanya super admin).
     */
    private function sources(): array
    {
        return [
            ['modul' => 'Produk', 'table' => 'tb_barang', 'id' => 'id_barang', 'nama' => 'nama', 'tenant' => 'id_sekolah', 'soft' => 'is_delete'],
            ['modul' => 'Kategori', 'table' => 'tb_kategori', 'id' => 'id_kategori', 'nama' => 'nama', 'tenant' => 'kelompok', 'soft' => 'is_delete'],
            ['modul' => 'Kelompok Kategori', 'table' => 'tb_kelompok_kategori', 'id' => 'id', 'nama' => 'nama_kelompok', 'tenant' => 'id_sekolah', 'soft' => null],
            ['modul' => 'Supplier', 'table' => 'tb_supplier', 'id' => 'id_supplier', 'nama' => 'nama', 'tenant' => 'id_sekolah', 'soft' => 'is_delete'],
            ['modul' => 'Pelanggan', 'table' => 'tb_pelanggan', 'id' => 'id_pelanggan', 'nama' => 'nama_pelanggan', 'tenant' => null, 'soft' => 'is_delete'],
            ['modul' => 'User', 'table' => 'tb_user', 'id' => 'id_user', 'nama' => 'nama_lengkap', 'tenant' => 'id_sekolah', 'soft' => null, 'deleted_at' => true],
            ['modul' => 'Pembelian', 'table' => 'tb_pembelian', 'id' => 'id_pembelian', 'nama' => 'nomor_faktur', 'tenant' => 'id_sekolah', 'soft' => 'is_delete'],
            ['modul' => 'Penjualan', 'table' => 'tb_penjualan', 'id' => 'id_penjualan', 'nama' => 'id_penjualan', 'tenant' => 'id_sekolah', 'soft' => 'is_delete'],
        ];
    }

    /** GET /audit — halaman */
    public function index(Request $request)
    {
        $this->authorizeView($request);
        $user = $request->user()->loadMissing(['sekolah', 'role']);

        return Inertia::render('Audit', [
            'sekolah' => $user->sekolah ? [
                'id_sekolah' => $user->sekolah->id_sekolah,
                'nama_sekolah' => $user->sekolah->nama_sekolah,
            ] : null,
            'is_super_admin' => $this->isDeveloper($request),
            'modul_list' => array_column($this->sources(), 'modul'),
        ]);
    }

    /** GET /audit/data — JSON gabungan + filter + pagination */
    public function data(Request $request)
    {
        $this->authorizeView($request);
        $sid = $request->user()->id_sekolah;
        $isDeveloper = $this->isDeveloper($request);

        $events = collect();

        foreach ($this->sources() as $src) {
            $t = $src['table'];

            // Kolom audit tidak seragam antar tabel: pilih yang ada, NULL bila tidak.
            $cols = DB::getSchemaBuilder()->getColumnListing($t);
            $base = DB::table("{$t} as r")
                ->selectRaw("r.{$src['id']} as ref_id, CAST(r.{$src['nama']} AS CHAR) as nama")
                ->limit(self::PER_SOURCE);
            foreach (['created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_at', 'deleted_by', $src['soft']] as $c) {
                if (! $c) {
                    continue;
                }
                if (in_array($c, $cols, true)) {
                    $base->addSelect("r.{$c}");
                } else {
                    $base->selectRaw("NULL as {$c}");
                }
            }

            if (! $isDeveloper) {
                if ($src['tenant'] === 'id_sekolah') {
                    $base->where("r.{$src['tenant']}", $sid);
                } elseif ($src['tenant'] === 'kelompok') {
                    $base->leftJoin('tb_kelompok_kategori as kk', 'kk.id', '=', 'r.id_kelompok')
                        ->where(function ($q) use ($sid) {
                            $q->whereNull('r.id_kelompok')->orWhere('kk.id_sekolah', $sid);
                        });
                } elseif ($src['modul'] === 'Pelanggan') {
                    // tb_pelanggan tanpa tenant: hanya tampil bila kelompoknya milik tenant / tanpa kelompok
                    $base->where(function ($q) use ($sid) {
                        $q->whereNull('r.id_kelompok_pelanggan')
                            ->orWhereIn('r.id_kelompok_pelanggan', function ($sub) use ($sid) {
                                $sub->select('id')->from('tb_kelompok_pelanggan')->where('id_sekolah', $sid);
                            });
                    });
                }
            }

            foreach ($base->orderByDesc('r.created_at')->get() as $row) {
                $label = "{$src['modul']} #{$row->ref_id}".($row->nama ? " — {$row->nama}" : '');

                if ($row->created_at) {
                    $events->push([
                        'waktu' => $row->created_at,
                        'aktor_id' => $row->created_by,
                        'aksi' => 'tambah',
                        'modul' => $src['modul'],
                        'detail' => "Menambah {$label}",
                    ]);
                }
                if ($row->updated_by) {
                    $events->push([
                        'waktu' => $row->updated_at ?? $row->created_at,
                        'aktor_id' => $row->updated_by,
                        'aksi' => 'edit',
                        'modul' => $src['modul'],
                        'detail' => "Mengubah {$label}",
                    ]);
                }
                $terhapus = ($src['soft'] && ($row->{$src['soft']} ?? null) == 1)
                    || (! empty($row->deleted_at));
                // tb_user & tabel tanpa is_delete: pakai deleted_at
                if (! $src['soft'] && ! empty($row->deleted_at)) {
                    $terhapus = true;
                }
                if ($terhapus && ($row->deleted_by || $row->deleted_at)) {
                    $events->push([
                        'waktu' => $row->deleted_at ?? $row->updated_at ?? $row->created_at,
                        'aktor_id' => $row->deleted_by,
                        'aksi' => 'hapus',
                        'modul' => $src['modul'],
                        'detail' => "Menghapus {$label}",
                    ]);
                }
            }
        }

        // Nama aktor sekaligus
        $ids = $events->pluck('aktor_id')->filter()->unique()->values();
        $aktor = $ids->isEmpty() ? collect() : DB::table('tb_user')
            ->whereIn('id_user', $ids)
            ->pluck('nama_lengkap', 'id_user');

        $events = $events
            ->map(fn ($e) => [
                'waktu' => $e['waktu'],
                'aktor' => $e['aktor_id'] ? ($aktor[$e['aktor_id']] ?? 'User #'.$e['aktor_id']) : '-',
                'aksi' => $e['aksi'],
                'modul' => $e['modul'],
                'detail' => $e['detail'],
            ])
            ->when($request->filled('modul'), fn ($c) => $c->where('modul', $request->query('modul')))
            ->when($request->filled('aksi'), fn ($c) => $c->where('aksi', $request->query('aksi')))
            ->when($request->filled('search'), function ($c) use ($request) {
                $s = strtolower($request->query('search'));

                return $c->filter(fn ($e) => str_contains(strtolower($e['detail'].' '.$e['aktor']), $s));
            })
            ->sortByDesc('waktu')
            ->values();

        $page = max(1, (int) $request->query('page', 1));
        $total = $events->count();

        return response()->json([
            'data' => $events->forPage($page, self::PER_PAGE)->values(),
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / self::PER_PAGE)),
            'total' => $total,
        ]);
    }
}
