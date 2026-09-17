<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Sekolah;
use App\Models\TbUser;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{

    /** Lihat user: developer, super admin, admin. Kasir dilarang. */
    private function authorizeLihat(Request $request): void
    {
        $role = $request->user()->role?->nama_role;
        if (! in_array($role, ['developer', 'super admin', 'admin'], true)) {
            abort(403, 'Anda tidak memiliki akses ke manajemen user.');
        }
    }

    /** Reset password: developer & super admin saja. Admin dilarang. */
    private function authorizeReset(Request $request): void
    {
        $role = $request->user()->role?->nama_role;
        if (! in_array($role, ['developer', 'super admin'], true)) {
            abort(403, 'Hanya developer / super admin yang bisa reset password.');
        }
    }

    /** Kelola user (tambah/edit/hapus): developer & super admin saja. Admin/kasir dilarang. */
    private function authorizeKelola(Request $request): void
    {
        $role = $request->user()->role?->nama_role;
        if (! in_array($role, ['developer', 'super admin'], true)) {
            abort(403, 'Anda tidak memiliki akses kelola user.');
        }
    }

    private function isDeveloper(Request $request): bool
    {
        return Tenant::isDeveloper($request);
    }

    /** GET /user — halaman */
    public function index(Request $request)
    {
        $this->authorizeLihat($request);
        $role = $request->user()->role?->nama_role;
        $isDeveloper = $this->isDeveloper($request);

        return Inertia::render('User', [
            'sekolah' => Tenant::aktif($request),
            'roles' => Role::orderBy('id_role')->get(['id_role', 'nama_role']),
            'sekolah_list' => Tenant::daftar($request),
            'is_super_admin' => $isDeveloper,
            'role_saya' => $role,
            'bisa_kelola' => in_array($role, ['developer', 'super admin'], true),
        ]);
    }

    /** GET /user/data — JSON paginated + search + filter role/status */
    public function data(Request $request)
    {
        $this->authorizeLihat($request);
        [$sekolahId, $semua] = Tenant::resolve($request);

        $query = TbUser::valid()
            ->with(['role:id_role,nama_role', 'sekolah:id_sekolah,nama_sekolah'])
            ->tenant($sekolahId, $semua)
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->query('search');
                $q->where(function ($w) use ($s) {
                    $w->where('nama_lengkap', 'like', "%{$s}%")
                        ->orWhere('username', 'like', "%{$s}%");
                });
            })
            ->when($request->filled('id_role'), fn ($q) => $q->where('id_role', $request->query('id_role')))
            ->when($request->query('status') === 'aktif', fn ($q) => $q->where('is_active', true))
            ->when($request->query('status') === 'nonaktif', fn ($q) => $q->where('is_active', false))
            ->orderBy('nama_lengkap');

        $paginated = $query->paginate(10)->withQueryString();

        // Sembunyikan password
        $paginated->getCollection()->transform(fn ($u) => $u->makeHidden('password'));

        return response()->json($paginated);
    }

    private function usernameUniqueRule(Request $request, ?int $ignoreId, int $sekolahId)
    {
        return Rule::unique('tb_user', 'username')
            ->ignore($ignoreId, 'id_user')
            ->where(fn ($q) => $q->where('id_sekolah', $sekolahId)->whereNull('deleted_at'));
    }

    public function store(Request $request)
    {
        $this->authorizeKelola($request);
        $me = $request->user();
        $isDeveloper = $this->isDeveloper($request);
        [$aktifId, $semua] = Tenant::resolve($request);

        $sekolahId = $isDeveloper
            ? (int) ($request->input('id_sekolah') ?? $aktifId ?? $me->id_sekolah)
            : (int) $me->id_sekolah;

        $v = $request->validate([
            'id_sekolah' => ['nullable', 'integer', 'exists:tb_sekolah,id_sekolah'],
            'id_role' => ['required', 'integer', 'exists:roles,id_role'],
            'nama_lengkap' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', $this->usernameUniqueRule($request, null, $sekolahId)],
            'password' => ['required', 'string', 'min:6', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'username.unique' => 'Username sudah dipakai di sekolah ini.',
        ]);

        // Hanya developer yang boleh membuat developer.
        // Super admin boleh dibuat oleh developer & super admin (sesama sekolah), admin dilarang.
        if (! $isDeveloper) {
            $roleName = Role::where('id_role', $v['id_role'])->value('nama_role');
            $roleSaya = $me->role?->nama_role;
            if ($roleName === 'developer') {
                return back()->withErrors(['id_role' => 'Hanya developer yang bisa membuat developer.']);
            }
            if ($roleName === 'super admin' && $roleSaya !== 'super admin') {
                return back()->withErrors(['id_role' => 'Hanya developer / super admin yang bisa membuat super admin.']);
            }
        }

        TbUser::create([
            'id_sekolah' => $sekolahId,
            'id_role' => $v['id_role'],
            'nama_lengkap' => $v['nama_lengkap'],
            'username' => $v['username'],
            'password' => $v['password'], // cast 'hashed' otomatis
            'is_active' => $v['is_active'] ?? true,
            'created_by' => $me->id_user,
        ]);

        return back()->with('success', 'User berhasil ditambahkan.');
    }

    public function update(Request $request, int $id)
    {
        $this->authorizeKelola($request);
        $me = $request->user();
        $isDeveloper = $this->isDeveloper($request);

        $target = TbUser::valid()->tenant($me->id_sekolah, $isDeveloper)->findOrFail($id);

        $sekolahId = (int) $target->id_sekolah;

        $v = $request->validate([
            'id_role' => ['required', 'integer', 'exists:roles,id_role'],
            'nama_lengkap' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', $this->usernameUniqueRule($request, $id, $sekolahId)],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'username.unique' => 'Username sudah dipakai di sekolah ini.',
        ]);

        if (! $isDeveloper) {
            $roleName = Role::where('id_role', $v['id_role'])->value('nama_role');
            $roleSaya = $me->role?->nama_role;
            if ($roleName === 'developer') {
                return back()->withErrors(['id_role' => 'Hanya developer yang bisa menetapkan developer.']);
            }
            if ($roleName === 'super admin' && $roleSaya !== 'super admin') {
                return back()->withErrors(['id_role' => 'Hanya developer / super admin yang bisa menetapkan super admin.']);
            }
        }
        // Tidak boleh menonaktifkan diri sendiri
        if ($target->id_user === $me->id_user && isset($v['is_active']) && ! $v['is_active']) {
            return back()->withErrors(['is_active' => 'Tidak bisa menonaktifkan akun sendiri.']);
        }

        $target->update([
            'id_role' => $v['id_role'],
            'nama_lengkap' => $v['nama_lengkap'],
            'username' => $v['username'],
            ...(isset($v['password']) && $v['password'] ? ['password' => $v['password']] : []),
            ...(isset($v['is_active']) ? ['is_active' => $v['is_active']] : []),
            'updated_by' => $me->id_user,
        ]);

        return back()->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $this->authorizeKelola($request);
        $me = $request->user();

        $target = TbUser::valid()->tenant($me->id_sekolah, Tenant::isDeveloper($request))->findOrFail($id);

        if ($target->id_user === $me->id_user) {
            return back()->withErrors(['user' => 'Tidak bisa menghapus akun sendiri.']);
        }

        $target->update([
            'deleted_at' => now(),
            'deleted_by' => $me->id_user,
        ]);

        return back()->with('success', 'User berhasil dihapus.');
    }

    public function toggle(Request $request, int $id)
    {
        $this->authorizeKelola($request);
        $me = $request->user();

        $target = TbUser::valid()->tenant($me->id_sekolah, Tenant::isDeveloper($request))->findOrFail($id);

        if ($target->id_user === $me->id_user) {
            return back()->withErrors(['user' => 'Tidak bisa menonaktifkan akun sendiri.']);
        }

        $target->update(['is_active' => ! $target->is_active]);

        return back()->with('success', $target->is_active ? 'User diaktifkan.' : 'User dinonaktifkan.');
    }

    public function resetPassword(Request $request, int $id)
    {
        $this->authorizeReset($request);

        $target = TbUser::valid()->tenant($request->user()->id_sekolah, Tenant::isDeveloper($request))->findOrFail($id);

        $v = $request->validate([
            'password' => ['required', 'string', 'min:6', 'max:100', 'confirmed'],
        ]);

        $target->update(['password' => $v['password']]);

        return back()->with('success', "Password {$target->username} berhasil direset.");
    }
}
