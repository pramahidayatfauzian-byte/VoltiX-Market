<?php

use App\Models\Barang;
use App\Models\TbUser;
use Database\Seeders\PosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PosSeeder::class);
    Storage::fake('public');
});

test('produk create stores foto and lists it', function () {
    $admin = TbUser::where('username', 'admin1')->first();

    $this->actingAs($admin)->post(route('produk.store'), [
        'nama' => 'Brg Foto',
        'harga_jual' => 5000,
        'stok' => 3,
        'foto' => UploadedFile::fake()->image('produk.jpg'),
    ])->assertSessionHas('success');

    $b = Barang::where('nama', 'Brg Foto')->first();
    expect($b->foto)->not->toBeNull();
    Storage::disk('public')->assertExists($b->foto);

    // tampil di pencarian kasir
    $this->actingAs($admin)->getJson(route('kasir.produk', ['search' => 'Brg Foto']))
        ->assertOk()->assertJsonPath('data.0.nama', 'Brg Foto');
});

test('produk update replaces foto and invalid file rejected', function () {
    $admin = TbUser::where('username', 'admin1')->first();
    $b = Barang::create([
        'id_sekolah' => $admin->id_sekolah, 'nama' => 'Brg Ganti',
        'harga_jual' => 5000, 'stok' => 3, 'is_active' => true, 'is_delete' => 0,
    ]);

    // file bukan gambar ditolak
    $this->actingAs($admin)->post(route('produk.store'), [
        'nama' => 'Brg Jahat',
        'harga_jual' => 1000,
        'stok' => 1,
        'foto' => UploadedFile::fake()->create('x.exe', 100, 'application/x-msdownload'),
    ])->assertSessionHasErrors('foto');

    // ganti foto via POST + _method (multipart)
    $this->actingAs($admin)->post(route('produk.update', $b->id_barang), [
        '_method' => 'PUT',
        'nama' => 'Brg Ganti',
        'harga_jual' => 5000,
        'stok' => 3,
        'foto' => UploadedFile::fake()->image('baru.png'),
    ])->assertSessionHas('success');

    expect($b->fresh()->foto)->not->toBeNull();
    Storage::disk('public')->assertExists($b->fresh()->foto);
});

test('sekolah logo upload works', function () {
    $admin = TbUser::where('username', 'admin1')->first();

    $this->actingAs($admin)->post(route('pengaturan.sekolah', $admin->id_sekolah), [
        '_method' => 'PUT',
        'nama_sekolah' => 'SMKN 1 Tasikmalaya',
        'logo' => UploadedFile::fake()->image('logo.png'),
    ])->assertSessionHas('success');

    $logo = \App\Models\Sekolah::find($admin->id_sekolah)->logo;
    expect($logo)->not->toBeNull();
    Storage::disk('public')->assertExists($logo);
});
