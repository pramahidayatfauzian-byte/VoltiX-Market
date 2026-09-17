<?php

use App\Models\TbUser;
use Database\Seeders\PosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PosSeeder::class);
});

test('backup forbidden unless super admin', function () {
    // guest dulu (actingAs menempel pada panggilan berikutnya dalam satu tes)
    $this->get(route('pengaturan.backup'))->assertRedirect(route('login'));

    $admin = TbUser::where('username', 'admin1')->first();
    $kasir = TbUser::where('username', 'kasir1')->first();

    $this->actingAs($kasir)->get(route('pengaturan.backup'))->assertForbidden();
    $this->actingAs($admin)->get(route('pengaturan.backup'))->assertForbidden();
});

test('super admin downloads sql dump of real database', function () {
    // phpunit menimpa DB_DATABASE=:memory:; kembalikan ke db_zian
    // (dump bersifat read-only via --single-transaction)
    config(['database.connections.mysql.database' => 'db_zian']);

    $sa = TbUser::where('username', 'superadmin')->first();

    $res = $this->actingAs($sa)->get(route('pengaturan.backup'));
    $res->assertOk();
    expect($res->headers->get('Content-Type'))->toContain('application/sql');

    $isi = $res->streamedContent();
    expect($isi)->toContain('tb_sekolah')
        ->and($isi)->toContain('tb_user');
})->skip(
    ! is_file('C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe')
    && ! is_file('/usr/bin/mysqldump'),
    'mysqldump tidak tersedia'
);
