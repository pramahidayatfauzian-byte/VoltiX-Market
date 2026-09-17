<?php

use App\Models\Role;
use App\Models\TbUser;
use Database\Seeders\PosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PosSeeder::class);
});

test('user page requires auth and blocks kasir', function () {
    $this->get(route('user.index'))->assertRedirect(route('login'));

    $kasir = TbUser::where('username', 'kasir1')->first();
    $this->actingAs($kasir)->get(route('user.index'))->assertForbidden();
    $this->actingAs($kasir)->getJson(route('user.data'))->assertForbidden();
});

test('admin can create user with hashed password', function () {
    $admin = TbUser::where('username', 'admin1')->first();
    $kasirRole = Role::where('nama_role', 'kasir')->first();

    $this->actingAs($admin)->post(route('user.store'), [
        'id_role' => $kasirRole->id_role,
        'nama_lengkap' => 'Kasir Baru',
        'username' => 'kasirbaru',
        'password' => 'rahasia123',
    ])->assertSessionHas('success');

    $u = TbUser::where('username', 'kasirbaru')->first();
    expect($u)->not->toBeNull()
        ->and((int) $u->id_sekolah)->toBe((int) $admin->id_sekolah)
        ->and(Hash::check('rahasia123', $u->getRawOriginal('password')))->toBeTrue();
});

test('username must be unique per school', function () {
    $admin = TbUser::where('username', 'admin1')->first();
    $kasirRole = Role::where('nama_role', 'kasir')->first();

    $this->actingAs($admin)->post(route('user.store'), [
        'id_role' => $kasirRole->id_role,
        'nama_lengkap' => 'Duplikat',
        'username' => 'kasir1',
        'password' => 'rahasia123',
    ]);

    // kasir1 milik sekolah lain (seed) -> boleh jika beda sekolah,
    // tapi buat user dengan username admin1 yang satu sekolah -> harus gagal
    $this->actingAs($admin)->post(route('user.store'), [
        'id_role' => $kasirRole->id_role,
        'nama_lengkap' => 'Duplikat2',
        'username' => 'admin1',
        'password' => 'rahasia123',
    ])->assertSessionHasErrors('username');
});

test('toggle reset and delete work with self protection', function () {
    $admin = TbUser::where('username', 'admin1')->first();
    $kasirRole = Role::where('nama_role', 'kasir')->first();

    $target = TbUser::create([
        'id_sekolah' => $admin->id_sekolah,
        'id_role' => $kasirRole->id_role,
        'nama_lengkap' => 'Target',
        'username' => 'target1',
        'password' => 'secret123',
        'is_active' => true,
    ]);

    // toggle
    $this->actingAs($admin)->patch(route('user.toggle', $target->id_user))
        ->assertSessionHas('success');
    expect($target->fresh()->is_active)->toBeFalse();

    // reset password
    $this->actingAs($admin)->post(route('user.reset', $target->id_user), [
        'password' => 'baru1234',
        'password_confirmation' => 'baru1234',
    ])->assertSessionHas('success');
    expect(Hash::check('baru1234', $target->fresh()->getRawOriginal('password')))->toBeTrue();

    // cannot delete self
    $this->actingAs($admin)->delete(route('user.destroy', $admin->id_user))
        ->assertSessionHasErrors('user');

    // delete target (soft)
    $this->actingAs($admin)->delete(route('user.destroy', $target->id_user))
        ->assertSessionHas('success');
    expect($target->fresh()->deleted_at)->not->toBeNull();

    // deleted user cannot login
    $this->post(route('logout'));
    $this->post(route('login.store'), [
        'id_sekolah' => $admin->id_sekolah,
        'username' => 'target1',
        'password' => 'baru1234',
    ]);
    $this->assertGuest();
});

test('admin cannot create super admin', function () {
    $admin = TbUser::where('username', 'admin1')->first();
    $saRole = Role::where('nama_role', 'super admin')->first();

    $this->actingAs($admin)->post(route('user.store'), [
        'id_role' => $saRole->id_role,
        'nama_lengkap' => 'SA Ilegal',
        'username' => 'sailegal',
        'password' => 'rahasia123',
    ])->assertSessionHasErrors('id_role');
});
