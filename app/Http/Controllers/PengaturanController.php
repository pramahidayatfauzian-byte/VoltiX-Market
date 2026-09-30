<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Sekolah;
use App\Models\TbUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\Process;

class PengaturanController extends Controller
{
    private function isDeveloper(Request $request): bool
    {
        return ($request->user()->role?->nama_role === 'developer');
    }

    private function isSuperAdmin(Request $request): bool
    {
        return ($request->user()->role?->nama_role === 'super admin');
    }

    private function authorizeView(Request $request): void
    {
        $role = $request->user()->role?->nama_role;
        if (! in_array($role, ['developer', 'super admin'], true)) {
            abort(403, 'Anda tidak memiliki akses ke pengaturan.');
        }
    }

    /** Developer & super admin boleh edit profil sekolah sendiri. Admin dilarang. */
    private function authorizeKelolaSekolah(Request $request): void
    {
        $role = $request->user()->role?->nama_role;
        if (! in_array($role, ['developer', 'super admin'], true)) {
            abort(403, 'Anda tidak memiliki akses mengatur sekolah.');
        }
    }

    /** GET /pengaturan — halaman */
    public function index(Request $request)
    {
        $this->authorizeView($request);
        $user = $request->user()->loadMissing(['sekolah', 'role']);
        $isDeveloper = $this->isDeveloper($request);
        $isSuperAdmin = $this->isSuperAdmin($request);

        // Developer global (id_sekolah NULL) → default ke sekolah pertama bila tanpa pilihan.
        if ($isDeveloper) {
            $targetId = $request->query('id_sekolah') ?: $user->id_sekolah;
            if (! $targetId) {
                $targetId = Sekolah::where('is_active', true)->orderBy('id_sekolah')->value('id_sekolah');
            }
            $target = Sekolah::findOrFail((int) $targetId);
        } else {
            $target = Sekolah::findOrFail((int) $user->id_sekolah);
        }

        $backup = null;
        if ($isDeveloper) {
            try {
                $info = DB::connection('mysql')->selectOne(
                    'SELECT COUNT(*) AS tabel, ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS mb
                     FROM information_schema.tables WHERE table_schema = ?',
                    [config('database.connections.mysql.database')]
                );
                $backup = [
                    'database' => config('database.connections.mysql.database'),
                    'tabel' => (int) ($info->tabel ?? 0),
                    'ukuran' => ($info->mb ?? 0).' MB',
                ];
            } catch (\Throwable) {
                $backup = null;
            }
        }

        return Inertia::render('Pengaturan', [
            'backup' => $backup,
            'sekolah' => $user->sekolah ? [
                'id_sekolah' => $user->sekolah->id_sekolah,
                'nama_sekolah' => $user->sekolah->nama_sekolah,
            ] : null,
            'sekolah_list' => $isDeveloper
                ? Sekolah::where('is_active', true)->orderBy('nama_sekolah')->get(['id_sekolah', 'nama_sekolah'])
                : collect(),
            'is_super_admin' => $isDeveloper,
            'is_super_admin_role' => $isSuperAdmin,
            'role_saya' => $user->role?->nama_role,
            'bisa_kelola_sekolah' => in_array($user->role?->nama_role, ['developer', 'super admin'], true),
            'target' => [
                'id_sekolah' => $target->id_sekolah,
                'kode_sekolah' => $target->kode_sekolah,
                'nama_sekolah' => $target->nama_sekolah,
                'alamat_sekolah' => $target->alamat_sekolah,
                'website' => $target->website,
                'logo_url' => $target->logo ? asset('storage/'.$target->logo) : null,
                'qris_url' => $target->qris_image ? asset('storage/'.$target->qris_image) : null,
            ],
            'me' => [
                'nama_lengkap' => $user->nama_lengkap,
                'username' => $user->username,
            ],
        ]);
    }

    /** POST /pengaturan/sekolah — tambah sekolah baru + buatkan akun
     * kasir, admin & super admin-nya. Khusus developer. */
    public function storeSekolah(Request $request)
    {
        if (! $this->isDeveloper($request)) {
            abort(403, 'Hanya developer yang bisa menambah sekolah.');
        }

        $v = $request->validate([
            'kode_sekolah' => ['required', 'string', 'max:20', 'unique:tb_sekolah,kode_sekolah'],
            'nama_sekolah' => ['required', 'string', 'max:150'],
            'alamat_sekolah' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'string', 'max:200'],
            'logo' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ], [
            'kode_sekolah.unique' => 'Kode sekolah sudah dipakai.',
            'logo.image' => 'Logo harus berupa file gambar.',
            'logo.mimes' => 'Logo harus berformat jpg, jpeg, png, atau webp.',
            'logo.max' => 'Ukuran logo maksimal 2MB.',
        ]);

        $roleKasir = Role::where('nama_role', 'kasir')->first();
        $roleAdmin = Role::where('nama_role', 'admin')->first();
        $roleSuper = Role::where('nama_role', 'super admin')->first();
        if (! $roleKasir || ! $roleAdmin || ! $roleSuper) {
            return back()->withErrors(['nama_sekolah' => 'Role kasir/admin/super admin belum tersedia di database.'])->withInput();
        }

        $kode = strtoupper(trim($v['kode_sekolah']));
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '', $kode));
        $password = $slug.'123';

        try {
            $akun = DB::transaction(function () use ($request, $v, $kode, $slug, $password, $roleKasir, $roleAdmin, $roleSuper) {
                $logo = $request->hasFile('logo') ? $request->file('logo')->store('sekolah', 'public') : null;

                $sekolah = Sekolah::create([
                    'kode_sekolah' => $kode,
                    'nama_sekolah' => $v['nama_sekolah'],
                    'alamat_sekolah' => $v['alamat_sekolah'] ?? null,
                    'website' => $v['website'] ?? null,
                    'logo' => $logo,
                    'is_active' => true,
                ]);

                $dibuat = [];
                foreach ([
                    ['role' => $roleKasir, 'slug' => 'kasir', 'nama' => 'Kasir'],
                    ['role' => $roleAdmin, 'slug' => 'admin', 'nama' => 'Admin'],
                    ['role' => $roleSuper, 'slug' => 'superadmin', 'nama' => 'Super Admin'],
                ] as $a) {
                    $username = $slug.'.'.$a['slug'];
                    // Hindari tabrakan bila kode dipakai ulang / username sudah ada.
                    $counter = 1;
                    $basis = $username;
                    while (TbUser::where('id_sekolah', $sekolah->id_sekolah)->where('username', $username)->whereNull('deleted_at')->exists()) {
                        $counter++;
                        $username = $basis.$counter;
                    }
                    TbUser::create([
                        'id_sekolah' => $sekolah->id_sekolah,
                        'id_role' => $a['role']->id_role,
                        'nama_lengkap' => $a['nama'].' '.$kode,
                        'username' => $username,
                        'password' => $password, // cast 'hashed' otomatis
                        'is_active' => true,
                        'created_by' => $request->user()->id_user,
                    ]);
                    $dibuat[] = ['role' => $a['nama'], 'username' => $username];
                }

                return [
                    'sekolah' => $sekolah->nama_sekolah,
                    'password' => $password,
                    'akun' => $dibuat,
                ];
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['nama_sekolah' => 'Gagal menambah sekolah. Silakan coba lagi.'])->withInput();
        }

        return back()->with('success', "Sekolah {$akun['sekolah']} berhasil ditambahkan beserta 3 akunnya.")->with('akun_baru', $akun);
    }

    /** PUT /pengaturan/sekolah/{id} — edit profil sekolah (developer & super admin saja) */
    public function updateSekolah(Request $request, int $id)
    {
        $this->authorizeKelolaSekolah($request);
        $user = $request->user();

        if (! $this->isDeveloper($request) && (int) $id !== (int) $user->id_sekolah) {
            abort(403, 'Anda hanya boleh mengatur sekolah sendiri.');
        }

        $v = $request->validate([
            'nama_sekolah' => ['required', 'string', 'max:150'],
            'alamat_sekolah' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'string', 'max:200'],
            'logo' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'qris_image' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ], [
            'logo.file' => 'Logo gagal diunggah. Coba lagi.',
            'logo.image' => 'Logo harus berupa file gambar.',
            'logo.mimes' => 'Logo harus berformat jpg, jpeg, png, atau webp.',
            'logo.max' => 'Ukuran logo maksimal 2MB.',
            'logo.uploaded' => 'Logo gagal diunggah. Pastikan ukuran di bawah 2MB dan koneksi stabil, lalu coba lagi.',
            'qris_image.image' => 'Gambar QRIS harus berupa file gambar.',
            'qris_image.mimes' => 'Gambar QRIS harus berformat jpg, jpeg, png, atau webp.',
            'qris_image.max' => 'Ukuran gambar QRIS maksimal 2MB.',
        ]);

        $sekolah = Sekolah::findOrFail($id);
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            if (! $file->isValid()) {
                return back()->withErrors(['logo' => 'Logo gagal diunggah (kode error: '.$file->getError().'). Coba file lain di bawah 2MB.']);
            }
            if ($sekolah->logo) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($sekolah->logo);
            }
            $v['logo'] = $file->store('sekolah', 'public');
            if (! $v['logo']) {
                return back()->withErrors(['logo' => 'Logo gagal disimpan ke server. Pastikan folder storage dapat ditulis.']);
            }
        } else {
            unset($v['logo']);
        }
        if ($request->hasFile('qris_image')) {
            $file = $request->file('qris_image');
            if (! $file->isValid()) {
                return back()->withErrors(['qris_image' => 'Gambar QRIS gagal diunggah (kode error: '.$file->getError().'). Coba file lain di bawah 2MB.']);
            }
            if ($sekolah->qris_image) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($sekolah->qris_image);
            }
            $v['qris_image'] = $file->store('sekolah', 'public');
            if (! $v['qris_image']) {
                return back()->withErrors(['qris_image' => 'Gambar QRIS gagal disimpan ke server. Pastikan folder storage dapat ditulis.']);
            }
        } else {
            unset($v['qris_image']);
        }
        $sekolah->update($v);

        return back()->with('success', 'Profil sekolah berhasil diperbarui.');
    }

    /** Cari binary mysqldump di lokasi umum (Windows/Linux). */
    private function findMysqldump(): ?string
    {
        $kandidat = [
            'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe',
            'C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
        ];
        foreach ($kandidat as $p) {
            if (@is_file($p)) {
                return $p;
            }
        }
        // fallback: cari di PATH
        $which = stripos(PHP_OS, 'WIN') === 0 ? 'where mysqldump' : 'command -v mysqldump';
        $out = [];
        @exec($which.' 2>&1', $out);
        foreach ($out as $line) {
            $line = trim($line);
            if ($line !== '' && @is_file($line)) {
                return $line;
            }
        }

        return null;
    }

    /**
     * GET /pengaturan/backup — unduh dump .sql database.
     * Khusus developer (dump memuat data SEMUA tenant).
     */
    public function backup(Request $request): BinaryFileResponse
    {
        if (! $this->isDeveloper($request)) {
            abort(403, 'Backup database hanya untuk developer.');
        }
        $cfg = config('database.connections.mysql');
        if (empty($cfg['database'])) {
            abort(422, 'Backup hanya tersedia untuk koneksi MySQL.');
        }

        $dump = $this->findMysqldump();
        if (! $dump) {
            abort(500, 'Binary mysqldump tidak ditemukan di server.');
        }

        $nama = 'backup-'.$cfg['database'].'-'.now()->format('Ymd-His').'.sql';
        $path = tempnam(sys_get_temp_dir(), 'posbackup').'.sql';

        $process = new Process([
            $dump,
            '--host='.$cfg['host'],
            '--port='.(string) ($cfg['port'] ?? 3306),
            '--user='.$cfg['username'],
            '--single-transaction',
            '--routines',
            '--result-file='.$path,
            $cfg['database'],
        ], null, ['MYSQL_PWD' => (string) ($cfg['password'] ?? '')]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($path) || filesize($path) === 0) {
            @unlink($path);
            report(new \RuntimeException('mysqldump gagal: '.$process->getErrorOutput()));
            abort(500, 'Backup gagal dibuat. Periksa log server.');
        }

        return response()->download($path, $nama, [
            'Content-Type' => 'application/sql',
        ])->deleteFileAfterSend(true);
    }

    /** GET /pengaturan/password — halaman ganti password tersendiri (super admin saja) */
    public function editPassword(Request $request)
    {
        $this->authorizeView($request);
        if ($this->isDeveloper($request)) {
            abort(403, 'Developer hanya boleh mengatur profil sekolah.');
        }
        $user = $request->user();

        return Inertia::render('PengaturanPassword', [
            'me' => [
                'nama_lengkap' => $user->nama_lengkap,
                'username' => $user->username,
            ],
        ]);
    }

    /** POST /pengaturan/password — ubah password sendiri (super admin saja) */
    public function updatePassword(Request $request)
    {
        $this->authorizeView($request);
        if ($this->isDeveloper($request)) {
            abort(403, 'Developer hanya boleh mengatur profil sekolah.');
        }
        $user = $request->user();

        $v = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'max:100', 'confirmed'],
        ]);

        $stored = (string) $user->getRawOriginal('password');
        $isBcrypt = str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$') || str_starts_with($stored, '$argon2');
        $valid = $isBcrypt
            ? Hash::check($v['current_password'], $stored)
            : hash_equals($stored, $v['current_password']);

        if (! $valid) {
            return back()->withErrors(['current_password' => 'Password saat ini salah.']);
        }

        $user->update(['password' => $v['password']]); // cast 'hashed'

        return back()->with('success', 'Password berhasil diubah.');
    }
}
