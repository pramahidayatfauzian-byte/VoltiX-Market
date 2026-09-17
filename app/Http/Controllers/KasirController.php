<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\KelompokPelanggan;
use App\Models\Pelanggan;
use App\Models\Penjualan;
use App\Support\KatalogProduk;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class KasirController extends Controller
{
    private function authorizeAkses(Request $request): void
    {
        // Semua role boleh akses kasir (developer global, super admin/admin/kasir per-sekolah).
    }

    /** Cari barang terdaftar di satu tenant (cocok longgar: abaikan nol di depan). */
    private function cariBarangTenant(string $barcode, int $idSekolah): ?Barang
    {
        $bersih = preg_replace('/\s+/', '', $barcode);
        return Barang::valid()
            ->where('id_sekolah', $idSekolah)
            ->where(function ($w) use ($bersih) {
                $w->where('barcode', $bersih)
                    ->orWhere('barcode', ltrim($bersih, '0'));
            })
            ->first([
                'id_barang', 'id_kategori', 'barcode', 'foto', 'nama', 'satuan',
                'harga_beli', 'harga_jual', 'stok',
            ]);
    }

    public function index(Request $request)
    {
        $this->authorizeAkses($request);
        $request->user()->loadMissing(['sekolah', 'role']);
        [$sekolahId, $semua] = Tenant::resolve($request);

        $kelompokQuery = KelompokPelanggan::query();
        if (! $semua && $sekolahId) {
            $kelompokQuery->where('id_sekolah', $sekolahId);
        }
        $kelompok = $kelompokQuery->orderBy('nama_kelompok')->get(['id', 'id_sekolah', 'nama_kelompok']);

        $kategoriQuery = Kategori::valid()->tenant($sekolahId, $semua)->orderBy('nama');
        $kategori = $kategoriQuery->get(['id_kategori', 'nama']);

        $produk = Barang::valid()
            ->tenant($sekolahId, $semua)
            ->where('is_active', true)
            ->orderBy('nama')
            ->limit(100)
            ->get([
                'id_barang', 'id_kategori', 'barcode', 'foto', 'nama', 'satuan',
                'harga_beli', 'harga_jual', 'stok',
            ])->map(fn ($b) => array_merge($b->toArray(), [
                'foto_url' => $b->foto ? asset('storage/'.$b->foto) : null,
            ]));

        return Inertia::render('Kasir', [
            'sekolah' => Tenant::aktif($request),
            'kelompok_pelanggan' => $kelompok,
            'kategori_list' => $kategori,
            'produk_list' => $produk,
        ]);
    }

    /** GET /kasir/produk?search=&id_kategori= — JSON untuk katalog/keranjang */
    public function searchProduk(Request $request)
    {
        $this->authorizeAkses($request);
        [$sekolahId, $semua] = Tenant::resolve($request);
        $search = trim((string) $request->query('search', ''));
        $searchRaw = preg_replace('/\s+/', '', $search);
        $searchNoZero = ltrim($searchRaw, '0');
        $idKategori = $request->query('id_kategori');

        $query = Barang::valid()
            ->tenant($sekolahId, $semua)
            ->where('is_active', true)
            ->when($idKategori, function ($q) use ($idKategori) {
                $q->where('id_kategori', $idKategori);
            })
            ->when($search !== '', function ($q) use ($search, $searchRaw, $searchNoZero) {
                $q->where(function ($w) use ($search, $searchRaw, $searchNoZero) {
                    $w->where('barcode', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$searchRaw}%")
                        ->orWhere('nama', 'like', "%{$search}%");
                    if ($searchNoZero !== '') {
                        $w->orWhere('barcode', 'like', "%{$searchNoZero}%");
                    }
                });
            })
            ->orderBy('nama')
            ->limit(100);

        $results = $query->get([
            'id_barang', 'id_kategori', 'barcode', 'foto', 'nama', 'satuan',
            'harga_beli', 'harga_jual', 'stok',
        ]);

        $kandidat = null;

        // Barcode / kata kunci tidak dikenal di database sekolah: coba kenali dari katalog.
        // Urutan: katalog master lokal (langsung dibuat & masuk keranjang) -> katalog
        // publik Food + Products paralel (jika ada nama, langsung dibuat) ->
        // kandidat kosong (tambah manual).
        if ($results->isEmpty() && $search !== '') {
            $master = null;
            if (preg_match('/^\d{6,}$/', $searchRaw)) {
                $master = $this->cariKatalogMaster($searchRaw);
            }
            if (! $master && strlen($search) >= 3) {
                $master = $this->cariKatalogMasterByName($search);
            }

            if ($master) {
                $targetSekolahId = ($sekolahId ?: $request->user()->id_sekolah) ?: 1;
                // Idempoten: barcode yang sudah terdaftar langsung dipakai (anti-duplikat).
                $ada = $this->cariBarangTenant($master['barcode'], $targetSekolahId);
                if ($ada) {
                    $results = collect([$ada]);
                } else {
                    $kategori = KatalogProduk::resolveKategori($master['kategori']);
                    $newBarang = Barang::create([
                        'id_sekolah' => $targetSekolahId,
                        'barcode' => $master['barcode'],
                        'nama' => $master['nama'],
                        'id_kategori' => $kategori->id_kategori,
                        'satuan' => $master['satuan'] ?? 'pcs',
                        'harga_beli' => (int) round($master['harga'] * 0.8),
                        'harga_jual' => (int) $master['harga'],
                        'stok' => 100,
                        'is_active' => true,
                        'is_delete' => 0,
                        'created_by' => $request->user()->id_user ?? 1,
                    ]);

                    $results = collect([$newBarang]);
                }
            } elseif (preg_match('/^\d{6,}$/', $searchRaw)) {
                // Cari nama produk di katalog publik (Food + Products paralel, gratis).
                $off = KatalogProduk::cariKatalogPublik($searchRaw);
                if ($off && ! empty($off['nama'])) {
                    $targetSekolahId = ($sekolahId ?: $request->user()->id_sekolah) ?: 1;
                    $ada = $this->cariBarangTenant($searchRaw, $targetSekolahId);
                    if ($ada) {
                        $results = collect([$ada]);
                    } else {
                        $kategori = KatalogProduk::resolveKategori($off['kategori'] ?? 'Lainnya');
                        $defaultHarga = match ($off['kategori'] ?? '') {
                            'Minuman' => 4000,
                            'Makanan & Snack' => 5000,
                            default => 5000,
                        };
                        $newBarang = Barang::create([
                            'id_sekolah' => $targetSekolahId,
                            'barcode' => $searchRaw,
                            'nama' => $off['nama'],
                            'id_kategori' => $kategori->id_kategori,
                            'satuan' => $off['satuan'] ?? 'pcs',
                            'harga_beli' => (int) round($defaultHarga * 0.8),
                            'harga_jual' => $defaultHarga,
                            'stok' => 100,
                            'is_active' => true,
                            'is_delete' => 0,
                            'created_by' => $request->user()->id_user ?? 1,
                        ]);

                        $results = collect([$newBarang]);
                    }
                } else {
                    $kandidat = [
                        'barcode' => $searchRaw,
                        'nama' => $off['nama'] ?? null,
                        'kategori' => $off['kategori'] ?? 'Lainnya',
                        'satuan' => $off['satuan'] ?? 'pcs',
                        'harga' => null,
                        'sumber' => $off['sumber'] ?? null,
                        'barcode_valid' => $this->eanValid($searchRaw),
                        'produk_indonesia' => str_starts_with(ltrim($searchRaw, '0'), '899'),
                    ];
                }
            }
        }

        return response()->json([
            'data' => $results->map(fn ($b) => array_merge($b->toArray(), [
                'foto_url' => $b->foto ? asset('storage/'.$b->foto) : null,
                'baru' => isset($newBarang) && $b->id_barang === $newBarang->id_barang,
            ])),
            'kandidat' => $kandidat,
        ]);
    }

    /**
     * Simpan cepat produk baru dari hasil scan barcode di kasir.
     * Semua role boleh (termasuk kasir) — terkunci di sekolah sendiri.
     * Mengembalikan produk yang sudah ada jika barcode sudah terdaftar
     * (mencegah duplikat akibat tap ganda di HP).
     */
    public function storeCepat(Request $request)
    {
        $this->authorizeAkses($request);
        [$sekolahId, $semua] = Tenant::resolve($request);

        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:50'],
            'nama' => ['required', 'string', 'max:150'],
            'id_kategori' => ['nullable', 'integer', 'exists:tb_kategori,id_kategori'],
            'satuan' => ['nullable', 'string', 'max:20'],
            'harga_jual' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'stok' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        $targetSekolahId = ($sekolahId ?: $request->user()->id_sekolah) ?: 1;
        $barcodeBersih = preg_replace('/\s+/', '', $validated['barcode']);

        // Idempoten: barcode yang sudah ada langsung dikembalikan.
        $ada = Barang::valid()
            ->where('id_sekolah', $targetSekolahId)
            ->where(function ($w) use ($barcodeBersih) {
                $w->where('barcode', $barcodeBersih)
                    ->orWhere('barcode', ltrim($barcodeBersih, '0'));
            })
            ->first([
                'id_barang', 'id_kategori', 'barcode', 'foto', 'nama', 'satuan',
                'harga_beli', 'harga_jual', 'stok',
            ]);
        if ($ada) {
            return response()->json([
                'data' => array_merge($ada->toArray(), [
                    'foto_url' => $ada->foto ? asset('storage/'.$ada->foto) : null,
                ]),
                'sudah_ada' => true,
            ]);
        }

        $barang = Barang::create([
            'id_sekolah' => $targetSekolahId,
            'barcode' => $barcodeBersih,
            'nama' => $validated['nama'],
            'id_kategori' => $validated['id_kategori'] ?? null,
            'satuan' => $validated['satuan'] ?: 'pcs',
            'harga_beli' => (int) round(((float) $validated['harga_jual']) * 0.8),
            'harga_jual' => $validated['harga_jual'],
            'stok' => $validated['stok'],
            'is_active' => true,
            'is_delete' => 0,
            'created_by' => $request->user()->id_user ?? 1,
        ]);

        return response()->json([
            'data' => array_merge($barang->toArray(), ['foto_url' => null]),
            'sudah_ada' => false,
        ], 201);
    }

    /** Validasi digit cek EAN-8 / UPC-A / EAN-13 (mendeteksi salah pindai). */
    private function eanValid(string $barcodeRaw): bool
    {
        return KatalogProduk::eanValid($barcodeRaw);
    }

    /** Master Katalog Produk Makanan, Minuman & Retail Indonesia.
     * Data tunggal di App\Support\KatalogProduk::masterList(). */
    private function getKatalogMasterList(): array
    {
        return KatalogProduk::masterList();
    }

    private function cariKatalogMaster(string $barcodeRaw): ?array
    {
        return KatalogProduk::cariMaster($barcodeRaw);
    }

    private function cariKatalogMasterByName(string $query): ?array
    {
        return KatalogProduk::cariMasterByName($query);
    }


    /** GET /kasir/pelanggan-cari?search= — JSON untuk dropdown pelanggan di panel bayar */
    public function searchPelanggan(Request $request)
    {
        $this->authorizeAkses($request);
        [$sekolahId, $semua] = Tenant::resolve($request);
        $search = trim((string) $request->query('search', ''));

        $query = Pelanggan::valid()
            ->with('kelompok:id,nama_kelompok')
            ->when(! $semua && $sekolahId, function ($q) use ($sekolahId) {
                $q->where(function ($w) use ($sekolahId) {
                    $w->whereNull('id_kelompok_pelanggan')
                        ->orWhereIn('id_kelompok_pelanggan', function ($sub) use ($sekolahId) {
                            $sub->select('id')->from('tb_kelompok_pelanggan')
                                ->where('id_sekolah', $sekolahId);
                        });
                });
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('nama_pelanggan', 'like', "%{$search}%")
                        ->orWhere('telepon', 'like', "%{$search}%");
                });
            })
            ->orderBy('nama_pelanggan')
            ->limit(20);

        return response()->json(['data' => $query->get()]);
    }

    /** POST /kasir/checkout — simpan transaksi secara atomik */
    public function checkout(Request $request)
    {
        $this->authorizeAkses($request);
        $validated = $request->validate([
            'id_pelanggan' => ['nullable', 'integer', 'exists:tb_pelanggan,id_pelanggan'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id_barang' => ['required', 'integer', 'exists:tb_barang,id_barang'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.diskon_tipe' => ['nullable', 'in:persen,nominal'],
            'items.*.diskon_nilai' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'items.*.harga_custom' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'jenis_transaksi' => ['required', 'in:tunai,kredit'],
            'cara_bayar' => ['required', 'string', 'max:50'],
            'total_bayar' => ['required', 'numeric', 'min:0', 'max:100000000000'],
            'status_pembayaran' => ['required', 'in:sudah bayar,belum bayar'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'items.required' => 'Keranjang masih kosong.',
            'items.min' => 'Keranjang masih kosong.',
        ]);

        /** @var \App\Models\TbUser $user */
        $user = $request->user();
        [$sekolahId, $semua] = Tenant::resolve($request);
        $sekolahTulis = $sekolahId ?? $user->id_sekolah;

        try {
            $receipt = DB::transaction(function () use ($validated, $user, $sekolahId, $semua, $sekolahTulis) {
                // Validasi tenant pelanggan
                $idPelanggan = $validated['id_pelanggan'] ?? null;
                if ($idPelanggan && ! $semua) {
                    $ok = Pelanggan::valid()->where('id_pelanggan', $idPelanggan)
                        ->where(function ($q) use ($sekolahId) {
                            $q->whereNull('id_kelompok_pelanggan')
                                ->orWhereIn('id_kelompok_pelanggan', function ($sub) use ($sekolahId) {
                                    $sub->select('id')->from('tb_kelompok_pelanggan')
                                        ->where('id_sekolah', $sekolahId);
                                });
                        })->exists();
                    if (! $ok) {
                        abort(422, 'Pelanggan tidak valid untuk tenant ini.');
                    }
                }

                $detailRows = [];
                $totalFaktur = 0;

                foreach ($validated['items'] as $item) {
                    /** @var Barang $barang */
                    $barang = Barang::valid()->lockForUpdate()
                        ->findOrFail($item['id_barang']);

                    // Tenant check per barang
                    if (! $semua && (int) $barang->id_sekolah !== (int) $sekolahId) {
                        abort(422, "Produk {$barang->nama} bukan milik sekolah ini.");
                    }
                    if (! $barang->is_active) {
                        abort(422, "Produk {$barang->nama} nonaktif.");
                    }

                    $qty = (int) $item['qty'];
                    if ((int) $barang->stok < $qty) {
                        abort(422, "Stok {$barang->nama} tidak cukup (sisa {$barang->stok}).");
                    }

                    // Harga manual dari kasir (mis. grosir/nego); default harga master.
                    // Validasi stok & tenant tetap dari server agar tidak bisa dimanipulasi.
                    $hargaJual = array_key_exists('harga_custom', $item) && $item['harga_custom'] !== null
                        ? (float) $item['harga_custom']
                        : (float) $barang->harga_jual;
                    $bruto = $hargaJual * $qty;

                    $tipe = $item['diskon_tipe'] ?? null;
                    $nilai = (float) ($item['diskon_nilai'] ?? 0);
                    $nominal = 0;
                    if ($tipe === 'persen' && $nilai > 0) {
                        $nominal = min($bruto, $bruto * $nilai / 100);
                    } elseif ($tipe === 'nominal' && $nilai > 0) {
                        $nominal = min($bruto, $nilai);
                    } else {
                        $tipe = null;
                        $nilai = 0;
                    }

                    $subtotal = $bruto - $nominal;
                    $totalFaktur += $subtotal;

                    // Kurangi stok
                    $barang->decrement('stok', $qty);

                    $detailRows[] = [
                        'barang' => $barang,
                        'harga' => $hargaJual,
                        'qty' => $qty,
                        'tipe' => $tipe,
                        'nilai' => $nilai,
                        'nominal' => $nominal,
                        'subtotal' => $subtotal,
                    ];
                }

                $totalFaktur = round($totalFaktur, 2);
                $totalBayar = round((float) $validated['total_bayar'], 2);

                if ($validated['status_pembayaran'] === 'sudah bayar'
                    && $validated['jenis_transaksi'] === 'tunai'
                    && $totalBayar < $totalFaktur) {
                    abort(422, 'Total bayar kurang dari total faktur.');
                }

                $kembalian = max(0, $totalBayar - $totalFaktur);

                /** @var Penjualan $penjualan */
                $penjualan = Penjualan::create([
                    'id_sekolah' => $sekolahTulis,
                    'id_user' => $user->id_user,
                    'id_pelanggan' => $idPelanggan,
                    'tanggal_penjualan' => now(),
                    'total_faktur' => $totalFaktur,
                    'total_bayar' => $totalBayar,
                    'kembalian' => $kembalian,
                    'status_pembayaran' => $validated['status_pembayaran'],
                    'jenis_transaksi' => $validated['jenis_transaksi'],
                    'cara_bayar' => $validated['cara_bayar'],
                    'note' => $validated['note'] ?? null,
                    'created_by' => $user->id_user,
                    'is_delete' => 0,
                ]);

                foreach ($detailRows as $row) {
                    $penjualan->detail()->create([
                        'id_barang' => $row['barang']->id_barang,
                        'jumlah_barang' => $row['qty'],
                        'harga_beli' => $row['barang']->harga_beli,
                        'harga_jual' => $row['harga'],
                        'diskon_tipe' => $row['tipe'],
                        'diskon_nilai' => $row['nilai'],
                        'diskon_nominal' => $row['nominal'],
                        'subtotal' => $row['subtotal'],
                    ]);
                }

                $pelangganNama = $idPelanggan
                    ? \App\Models\Pelanggan::where('id_pelanggan', $idPelanggan)->value('nama_pelanggan')
                    : null;

                return [
                    'id_penjualan' => $penjualan->id_penjualan,
                    'tanggal' => $penjualan->tanggal_penjualan->format('d/m/Y H:i'),
                    'sekolah' => $user->sekolah?->nama_sekolah,
                    'alamat_sekolah' => $user->sekolah?->alamat_sekolah,
                    'kasir' => $user->nama_lengkap ?? $user->username,
                    'pelanggan' => $pelangganNama,
                    'jenis_transaksi' => $validated['jenis_transaksi'],
                    'cara_bayar' => $validated['cara_bayar'],
                    'status_pembayaran' => $validated['status_pembayaran'],
                    'note' => $validated['note'] ?? null,
                    'total_faktur' => $totalFaktur,
                    'total_bayar' => $totalBayar,
                    'kembalian' => $kembalian,
                    'total_diskon' => round(array_sum(array_column($detailRows, 'nominal')), 2),
                    'item_count' => count($detailRows),
                    'items' => array_map(fn ($row) => [
                        'nama' => $row['barang']->nama,
                        'qty' => $row['qty'],
                        'harga' => (float) $row['barang']->harga_jual,
                        'diskon' => $row['nominal'],
                        'subtotal' => $row['subtotal'],
                    ], $detailRows),
                ];
            });

            return back()
                ->with('success', "Transaksi #{$receipt['id_penjualan']} berhasil. Kembalian Rp ".number_format($receipt['kembalian'], 0, ',', '.'))
                ->with('receipt', $receipt);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return back()->withErrors(['items' => $e->getMessage()])->withInput();
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['items' => 'Transaksi gagal disimpan. Silakan coba lagi.'])->withInput();
        }
    }
}
