<?php

use App\Models\Sekolah;
use App\Models\TbUser;
use Database\Seeders\PosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PosSeeder::class);
});

test('login screen renders with sekolah list', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('user can login with correct sekolah username password', function () {
    $sekolah = Sekolah::first();
    $user = TbUser::where('username', 'superadmin')->first();

    $response = $this->post(route('login.store'), [
        'id_sekolah' => $sekolah->id_sekolah,
        'username' => 'superadmin',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('user cannot login with wrong sekolah', function () {
    $sekolahLain = Sekolah::orderBy('id_sekolah', 'desc')->first();
    $kasir1Sekolah = TbUser::where('username', 'kasir1')->first()->id_sekolah;

    // pastikan beda sekolah
    expect($sekolahLain->id_sekolah)->not->toBe($kasir1Sekolah);

    $this->post(route('login.store'), [
        'id_sekolah' => $sekolahLain->id_sekolah,
        'username' => 'kasir1',
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('user cannot login with wrong password', function () {
    $sekolah = Sekolah::first();

    $this->post(route('login.store'), [
        'id_sekolah' => $sekolah->id_sekolah,
        'username' => 'superadmin',
        'password' => 'salah',
    ]);

    $this->assertGuest();
});

test('dashboard requires auth', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('super admin can login without choosing sekolah', function () {
    $this->post(route('login.store'), [
        'id_sekolah' => null,
        'username' => 'superadmin',
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
});

test('kasir cannot login without sekolah', function () {
    $this->post(route('login.store'), [
        'id_sekolah' => null,
        'username' => 'kasir1',
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('user can logout via post', function () {
    $user = TbUser::where('username', 'superadmin')->first();

    $this->actingAs($user)->post(route('logout'));

    $this->assertGuest();
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});
