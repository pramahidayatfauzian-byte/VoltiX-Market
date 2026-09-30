<?php

namespace App\Http\Controllers;

use App\Models\Pelanggan;
use App\Models\Penjualan;
use App\Support\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /** Persen perubahan vs baseline; null bila tak ada pembanding. */
    private static function persen(float $baseline, float $sekarang): ?float
    {
        if ($baseline <= 0) {
            return $sekarang > 0 ? 100.0 : null;
        }

        return round(($sekarang - $baseline) / $baseline * 100, 1);
    }

    public function index(Request $request)
    {
        /** @var \App\Models\TbUser $user */
        $user = $request->user()->loadMissing(['sekolah', 'role']);
        $isDeveloper = Tenant::isDeveloper($request);
        [$sekolahId, $semua] = Tenant::resolve($request);

        $today = Carbon::today();

        // --- Card 1 & 2: penjualan hari ini (nominal + jumlah transaksi) ---
        $todayQuery = Penjualan::valid()->tenant($sekolahId, $semua)
            ->whereDate('tanggal_penjualan', $today);

        $penjualanHariIni = (float) (clone $todayQuery)->sum('total_faktur');
        $transaksiHariIni = (int) (clone $todayQuery)->count();

        // Tren vs kemarin (badge naik/turun ala referensi)
        $kemarin = Carbon::yesterday();
        $kemarinQuery = Penjualan::valid()->tenant($sekolahId, $semua)
            ->whereDate('tanggal_penjualan', $kemarin);
        $trenPenjualan = self::persen(
            (float) (clone $kemarinQuery)->sum('total_faktur'),
            $penjualanHariIni
        );
        $trenTransaksi = self::persen(
            (float) (clone $kemarinQuery)->count(),
            (float) $transaksiHariIni
        );

        // --- Card 3: produk terjual hari ini (sum qty) ---
        $produkTerjual = (int) DB::table('tb_detail_penjualan as d')
            ->join('tb_penjualan as p', 'p.id_penjualan', '=', 'd.id_penjualan')
            ->when(! $semua && $sekolahId, fn ($q) => $q->where('p.id_sekolah', $sekolahId))
            ->where(function ($q) {
                $q->whereNull('p.is_delete')->orWhere('p.is_delete', 0);
            })
            ->whereNull('p.deleted_at')
            ->whereDate('p.tanggal_penjualan', $today)
            ->sum('d.jumlah_barang');

        // --- Card 4: pelanggan ---
        // tb_pelanggan tidak punya id_sekolah; tenant via kelompok.
        // Data eksisting banyak yang id_kelompok_pelanggan NULL -> hitung sebagai global/shared.
        $pelangganQuery = Pelanggan::valid();
        if (! $semua && $sekolahId) {
            $pelangganQuery->where(function ($q) use ($sekolahId) {
                $q->whereNull('id_kelompok_pelanggan')
                    ->orWhereIn('id_kelompok_pelanggan', function ($sub) use ($sekolahId) {
                        $sub->select('id')->from('tb_kelompok_pelanggan')
                            ->where('id_sekolah', $sekolahId);
                    });
            });
        }
        $pelangganCount = (int) (clone $pelangganQuery)->count();

        // --- Grafik 7 hari terakhir ---
        $dates = collect(range(6, 0))->map(fn ($i) => Carbon::today()->subDays($i));
        $from = $dates->first()->startOfDay();

        $perDay = Penjualan::valid()->tenant($sekolahId, $semua)
            ->where('tanggal_penjualan', '>=', $from)
            ->selectRaw('DATE(tanggal_penjualan) as d, SUM(total_faktur) as total, COUNT(*) as transaksi')
            ->groupByRaw('DATE(tanggal_penjualan)')
            ->pluck('total', 'd');

        $perDayCount = Penjualan::valid()->tenant($sekolahId, $semua)
            ->where('tanggal_penjualan', '>=', $from)
            ->selectRaw('DATE(tanggal_penjualan) as d, COUNT(*) as transaksi')
            ->groupByRaw('DATE(tanggal_penjualan)')
            ->pluck('transaksi', 'd');

        $grafik = $dates->map(function (Carbon $date) use ($perDay, $perDayCount) {
            $key = $date->format('Y-m-d');

            return [
                'tanggal' => $key,
                'label' => $date->locale('id')->isoFormat('ddd, D/M'),
                'total' => (float) ($perDay[$key] ?? 0),
                'transaksi' => (int) ($perDayCount[$key] ?? 0),
            ];
        })->values();

        // --- Stok menipis (peringatan) ---
        $stokMenipis = \App\Models\Barang::valid()
            ->tenant($sekolahId, $semua)
            ->where('is_active', true)
            ->where('stok', '<=', \App\Models\Barang::BATAS_MENIPIS)
            ->orderBy('stok')
            ->orderBy('nama')
            ->limit(5)
            ->get(['id_barang', 'nama', 'stok', 'satuan'])
            ->map(fn ($b) => [
                'id_barang' => $b->id_barang,
                'nama' => $b->nama,
                'stok' => (int) $b->stok,
                'satuan' => $b->satuan,
                'habis' => ((int) $b->stok) <= 0,
            ]);

        // --- Transaksi terbaru ---
        $terbaru = Penjualan::valid()->tenant($sekolahId, $semua)
            ->with(['kasir:id_user,username,nama_lengkap'])
            ->orderByDesc('tanggal_penjualan')
            ->orderByDesc('id_penjualan')
            ->limit(8)
            ->get()
            ->map(fn ($p, $i) => [
                'no' => $i + 1,
                'id_penjualan' => $p->id_penjualan,
                'tanggal' => $p->tanggal_penjualan?->format('d/m/Y H:i'),
                'total' => (float) $p->total_faktur,
                'kasir' => $p->kasir?->nama_lengkap ?? $p->kasir?->username ?? '-',
                'status' => $p->status_pembayaran,
            ]);

        return Inertia::render('Dashboard', [
            'sekolah' => $user->sekolah ? [
                'id_sekolah' => $user->sekolah->id_sekolah,
                'nama_sekolah' => $user->sekolah->nama_sekolah,
            ] : null,
            'is_developer' => $isDeveloper,
            'sekolah_aktif_id' => $semua ? 'semua' : $sekolahId,
            'sistem' => $isDeveloper ? $this->ringkasanSistem() : null,
            'stats' => [
                'penjualan_hari_ini' => $penjualanHariIni,
                'transaksi_hari_ini' => $transaksiHariIni,
                'produk_terjual' => $produkTerjual,
                'pelanggan' => $pelangganCount,
                'is_super_admin' => $isDeveloper,
                'tren_penjualan' => $trenPenjualan,
                'tren_transaksi' => $trenTransaksi,
            ],
            'grafik' => $grafik,
            'transaksi_terbaru' => $terbaru,
            'stok_menipis' => $stokMenipis,
            'batas_menipis' => \App\Models\Barang::BATAS_MENIPIS,
        ]);
    }

    /** Ringkasan sistem untuk developer: sekolah, pengguna, server & database. */
    private function ringkasanSistem(): array
    {
        $sekolah = \App\Models\Sekolah::orderBy('nama_sekolah')
            ->get(['id_sekolah', 'kode_sekolah', 'nama_sekolah', 'logo', 'is_active'])
            ->map(function ($s) {
                return [
                    'id_sekolah' => $s->id_sekolah,
                    'kode_sekolah' => $s->kode_sekolah,
                    'nama_sekolah' => $s->nama_sekolah,
                    'logo_url' => $s->logo ? asset('storage/'.$s->logo) : null,
                    'is_active' => (bool) $s->is_active,
                    'total_pengguna' => \App\Models\TbUser::where('id_sekolah', $s->id_sekolah)->whereNull('deleted_at')->count(),
                    'total_produk' => \App\Models\Barang::valid()->where('id_sekolah', $s->id_sekolah)->count(),
                ];
            });

        $peran = \App\Models\TbUser::whereNull('deleted_at')
            ->join('roles', 'roles.id_role', '=', 'tb_user.id_role')
            ->groupBy('roles.nama_role')
            ->pluck(DB::raw('COUNT(*)'), 'roles.nama_role');

        $peranAktif = \App\Models\TbUser::whereNull('deleted_at')
            ->where('is_active', true)
            ->join('roles', 'roles.id_role', '=', 'tb_user.id_role')
            ->groupBy('roles.nama_role')
            ->pluck(DB::raw('COUNT(*)'), 'roles.nama_role');

        $totalAktif = (int) array_sum($peranAktif->all());

        $dbMb = 0;
        $dbOk = true;
        $dbVersi = '-';
        try {
            $info = DB::connection('mysql')->selectOne(
                'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS mb
                 FROM information_schema.tables WHERE table_schema = ?',
                [config('database.connections.mysql.database')]
            );
            $dbMb = (float) ($info->mb ?? 0);
            $ver = DB::connection('mysql')->selectOne('SELECT VERSION() AS v');
            $dbVersi = explode('-', (string) ($ver->v ?? ''))[0] ?: '-';
        } catch (\Throwable) {
            $dbOk = false;
        }

        return [
            'sekolah_list' => $sekolah,
            'total_sekolah' => $sekolah->count(),
            'total_pengguna' => (int) array_sum($peran->all()),
            'pengguna_peran' => $peran,
            'pengguna_aktif' => $totalAktif,
            'pengguna_nonaktif' => max(0, (int) array_sum($peran->all()) - $totalAktif),
            'pengguna_peran_aktif' => $peranAktif,
            'platform' => [
                'aplikasi' => config('app.name', 'VOLTIX'),
                'laravel' => app()->version(),
                'php' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'.'.PHP_RELEASE_VERSION,
                'os' => PHP_OS_FAMILY.' ('.php_uname('s').' '.php_uname('r').')',
                'server' => (string) (request()->server('SERVER_SOFTWARE') ?? '-'),
                'database' => 'MySQL '.$dbVersi,
                'zona_waktu' => config('app.timezone').' ('.now()->format('P').')',
            ],
            'server' => [
                'operasional' => $dbOk,
                'php' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'.'.PHP_RELEASE_VERSION,
                'database' => config('database.connections.mysql.database'),
                'ukuran_db' => $dbMb.' MB',
                'waktu' => now()->locale('id')->isoFormat('dddd, D MMMM YYYY HH:mm'),
            ],
        ];
    }
}
