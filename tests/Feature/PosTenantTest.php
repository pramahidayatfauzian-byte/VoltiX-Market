<?php

use App\Models\TbUser;
use Database\Seeders\PosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PosSeeder::class);
});

test('super admin sees all users and can reset password in any school', function () {
    $sa = TbUser::where('username', 'superadmin')->first();
    $target = TbUser::where('username', 'kasir1')->first(); // different school

    // default semula -> see all
    $res = $this->actingAs($sa)->getJson(route('user.data'));
    $res->assertOk();
    expect($res->json('total'))->toBeGreaterThanOrEqual(2);

    // reset password lintas sekolah berhasil (hash terupdate)
    $this->actingAs($sa)->post(route('user.reset', $target->id_user), [
        'password' => 'baru1234',
        'password_confirmation' => 'baru1234',
    ])->assertSessionHas('success');

    expect(Hash::check('baru1234', $target->fresh()->getRawOriginal('password')))->toBeTrue();

    // new password can login with correct school
    $this->post(route('login.store'), [
        'id_sekolah' => $target->id_sekolah,
        'username' => $target->username,
        'password' => 'baru1234',
    ]);
    $this->assertAuthenticated();
});

test('super admin can switch sekolah_aktif and list filters', function () {
    $sa = TbUser::where('username', 'superadmin')->first();
    $sekolah2 = \App\Models\Sekolah::orderBy('id_sekolah')->skip(1)->first();

    // switch to sekolah 2
    $this->actingAs($sa)->post(route('sekolah-aktif.update'), ['id_sekolah' => $sekolah2->id_sekolah])
        ->assertSessionHas('success');

    // user list now filtered to sekolah 2 only
    $res = $this->actingAs($sa)->getJson(route('user.data'));
    $res->assertOk();
    $ids = collect($res->json('data'))->pluck('id_sekolah')->unique()->values();
    expect($ids->count())->toBe(1)
        ->and($ids->first())->toBe($sekolah2->id_sekolah);

    // switch to semua -> see all again
    $this->actingAs($sa)->post(route('sekolah-aktif.update'), ['id_sekolah' => 'semua'])
        ->assertSessionHas('success');

    $res = $this->actingAs($sa)->getJson(route('user.data'));
    expect($res->json('total'))->toBeGreaterThan(1);
});

test('admin and kasir cannot switch sekolah', function () {
    $admin = TbUser::where('username', 'admin1')->first();
    $this->actingAs($admin)->post(route('sekolah-aktif.update'), ['id_sekolah' => 'semua'])
        ->assertSessionHasErrors('sekolah');

    $kasir = TbUser::where('username', 'kasir1')->first();
    $this->actingAs($kasir)->post(route('sekolah-aktif.update'), ['id_sekolah' => 1])
        ->assertSessionHasErrors('sekolah');
});
