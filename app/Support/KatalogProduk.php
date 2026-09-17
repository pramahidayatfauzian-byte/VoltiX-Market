<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Katalog pembantu pengenalan barcode produk Indonesia.
 *
 * Sumber tunggal data & logika yang dipakai bersama oleh:
 * - Kasir (tambah cepat ke keranjang),
 * - Produk (prefill form tambah barang dari hasil scan).
 *
 * Urutan pengenalan: katalog master lokal (offline) -> OpenFoodFacts
 * (publik, gratis, tanpa API key) -> kandidat kosong untuk isi manual.
 */
class KatalogProduk
{
    /** Validasi digit cek EAN-8 / UPC-A / EAN-13 (mendeteksi salah pindai). */
    public static function eanValid(string $barcodeRaw): bool
    {
        $d = preg_replace('/\D/', '', $barcodeRaw);
        $len = strlen($d);
        if (! in_array($len, [8, 12, 13], true)) {
            return false;
        }
        $cek = (int) substr($d, -1);
        $isi = substr($d, 0, -1);
        $jumlah = 0;
        $posisiDariKanan = 1;
        for ($i = strlen($isi) - 1; $i >= 0; $i--) {
            $angka = (int) $isi[$i];
            $jumlah += ($posisiDariKanan % 2 === 1) ? $angka * 3 : $angka;
            $posisiDariKanan++;
        }
        return (10 - ($jumlah % 10)) % 10 === $cek;
    }

    /** Master Katalog Produk Makanan, Minuman & Retail Indonesia */
    public static function masterList(): array
    {
        return [
            // Cleo Air Murni & Mineral Indonesia
            '8996129809131' => ['nama' => 'Cleo Air Murni Botol 220ml', 'kategori' => 'Minuman', 'harga' => 2500, 'satuan' => 'botol'],
            '8996129800000' => ['nama' => 'Cleo Air Murni Cup Gelas 220ml', 'kategori' => 'Minuman', 'harga' => 1000, 'satuan' => 'cup'],
            '8996129800017' => ['nama' => 'Cleo Air Murni Cup Gelas 220ml', 'kategori' => 'Minuman', 'harga' => 1000, 'satuan' => 'cup'],
            '8996129800024' => ['nama' => 'Cleo Air Murni Cup Gelas 220ml', 'kategori' => 'Minuman', 'harga' => 1000, 'satuan' => 'cup'],
            '8996129803504' => ['nama' => 'Cleo Air Murni Botol 330ml', 'kategori' => 'Minuman', 'harga' => 3000, 'satuan' => 'botol'],
            '8996129802569' => ['nama' => 'Cleo Air Murni Botol 550ml', 'kategori' => 'Minuman', 'harga' => 3500, 'satuan' => 'botol'],
            '8996129800640' => ['nama' => 'Cleo Air Murni Botol 1500ml', 'kategori' => 'Minuman', 'harga' => 6000, 'satuan' => 'botol'],

            // Air Mineral & Minuman Dalam Kemasan
            '8886008101053' => ['nama' => 'Aqua Air Mineral Botol 600ml', 'kategori' => 'Minuman', 'harga' => 3500, 'satuan' => 'botol'],
            '8886008101046' => ['nama' => 'Aqua Air Mineral Cup Gelas 240ml', 'kategori' => 'Minuman', 'harga' => 1000, 'satuan' => 'cup'],
            '8886008101022' => ['nama' => 'Aqua Air Mineral Botol 330ml', 'kategori' => 'Minuman', 'harga' => 2500, 'satuan' => 'botol'],
            '8886008101060' => ['nama' => 'Aqua Air Mineral Botol 1500ml', 'kategori' => 'Minuman', 'harga' => 6500, 'satuan' => 'botol'],
            '8992775211019' => ['nama' => 'Aqua Air Mineral Botol 600ml', 'kategori' => 'Minuman', 'harga' => 3500, 'satuan' => 'botol'],
            '8996001600269' => ['nama' => 'Aqua Air Mineral Botol 600ml', 'kategori' => 'Minuman', 'harga' => 3500, 'satuan' => 'botol'],
            '8996001414006' => ['nama' => 'Le Minerale Air Mineral 600ml', 'kategori' => 'Minuman', 'harga' => 3500, 'satuan' => 'botol'],
            '8996001414013' => ['nama' => 'Le Minerale Air Mineral 330ml', 'kategori' => 'Minuman', 'harga' => 2500, 'satuan' => 'botol'],
            '8996001414020' => ['nama' => 'Le Minerale Air Mineral 1500ml', 'kategori' => 'Minuman', 'harga' => 6500, 'satuan' => 'botol'],
            '8998866101056' => ['nama' => 'Club Air Mineral Botol 600ml', 'kategori' => 'Minuman', 'harga' => 3000, 'satuan' => 'botol'],
            '8998866101049' => ['nama' => 'Club Air Mineral Cup 220ml', 'kategori' => 'Minuman', 'harga' => 1000, 'satuan' => 'cup'],
            '8997017230018' => ['nama' => 'Pristine 8.6+ Air Mineral 400ml', 'kategori' => 'Minuman', 'harga' => 4500, 'satuan' => 'botol'],
            '8997017230025' => ['nama' => 'Pristine 8.6+ Air Mineral 600ml', 'kategori' => 'Minuman', 'harga' => 5500, 'satuan' => 'botol'],

            // Teh & Kopi Siap Minum
            '8996006856869' => ['nama' => 'Teh Botol Sosro Kotak 330ml', 'kategori' => 'Minuman', 'harga' => 4000, 'satuan' => 'kotak'],
            '8996006856852' => ['nama' => 'Teh Botol Sosro Kotak 250ml', 'kategori' => 'Minuman', 'harga' => 3500, 'satuan' => 'kotak'],
            '8992741984213' => ['nama' => 'Teh Botol Sosro PET 350ml', 'kategori' => 'Minuman', 'harga' => 4500, 'satuan' => 'botol'],
            '8992741984206' => ['nama' => 'Teh Botol Sosro PET 450ml', 'kategori' => 'Minuman', 'harga' => 5500, 'satuan' => 'botol'],
            '8996001600263' => ['nama' => 'Teh Pucuk Harum Melati 350ml', 'kategori' => 'Minuman', 'harga' => 4000, 'satuan' => 'botol'],
            '8996001600379' => ['nama' => 'Teh Pucuk Harum Melati 500ml', 'kategori' => 'Minuman', 'harga' => 6000, 'satuan' => 'botol'],
            '8992741974108' => ['nama' => 'Fruit Tea Apel PET 350ml', 'kategori' => 'Minuman', 'harga' => 4500, 'satuan' => 'botol'],
            '8992741974207' => ['nama' => 'Fruit Tea Blackcurrant PET 350ml', 'kategori' => 'Minuman', 'harga' => 4500, 'satuan' => 'botol'],
            '8992741974306' => ['nama' => 'Fruit Tea Freeze PET 350ml', 'kategori' => 'Minuman', 'harga' => 4500, 'satuan' => 'botol'],
            '8991002201019' => ['nama' => 'Teh Gelas Cup 180ml', 'kategori' => 'Minuman', 'harga' => 1500, 'satuan' => 'cup'],
            '8992772010011' => ['nama' => 'Teh Rio Cup 180ml', 'kategori' => 'Minuman', 'harga' => 1500, 'satuan' => 'cup'],
            '8998888360011' => ['nama' => 'Floridina Orange Pulpy 350ml', 'kategori' => 'Minuman', 'harga' => 3500, 'satuan' => 'botol'],
            '8998888200010' => ['nama' => 'Ale-Ale Anggur Cup 180ml', 'kategori' => 'Minuman', 'harga' => 1500, 'satuan' => 'cup'],
            '8998888200027' => ['nama' => 'Ale-Ale Jeruk Cup 180ml', 'kategori' => 'Minuman', 'harga' => 1500, 'satuan' => 'cup'],
            '8992753100014' => ['nama' => 'Okky Jelly Drink Jambu 150ml', 'kategori' => 'Minuman', 'harga' => 1500, 'satuan' => 'cup'],
            '8992753100021' => ['nama' => 'Okky Jelly Drink Jeruk 150ml', 'kategori' => 'Minuman', 'harga' => 1500, 'satuan' => 'cup'],

            // Susu & Isotonik
            '8998009010569' => ['nama' => 'Ultra Milk UHT Coklat 200ml', 'kategori' => 'Minuman', 'harga' => 6000, 'satuan' => 'kotak'],
            '8998009010552' => ['nama' => 'Ultra Milk UHT Full Cream 200ml', 'kategori' => 'Minuman', 'harga' => 6000, 'satuan' => 'kotak'],
            '8998009010576' => ['nama' => 'Ultra Milk UHT Strawberry 200ml', 'kategori' => 'Minuman', 'harga' => 6000, 'satuan' => 'kotak'],
            '8998009010613' => ['nama' => 'Ultra Milk UHT Plain 1000ml', 'kategori' => 'Minuman', 'harga' => 21000, 'satuan' => 'kotak'],
            '8998009010620' => ['nama' => 'Ultra Milk UHT Coklat 1000ml', 'kategori' => 'Minuman', 'harga' => 21000, 'satuan' => 'kotak'],
            '8992723001013' => ['nama' => 'Indomilk UHT Coklat 190ml', 'kategori' => 'Minuman', 'harga' => 5000, 'satuan' => 'kotak'],
            '8992723001020' => ['nama' => 'Indomilk UHT Vanila 190ml', 'kategori' => 'Minuman', 'harga' => 5000, 'satuan' => 'kotak'],
            '8992696400011' => ['nama' => 'Bear Brand Susu Steril 189ml', 'kategori' => 'Minuman', 'harga' => 11000, 'satuan' => 'kaleng'],
            '8992696404118' => ['nama' => 'Milo Activ-Go UHT 180ml', 'kategori' => 'Minuman', 'harga' => 5500, 'satuan' => 'kotak'],
            '8992696404125' => ['nama' => 'Milo Activ-Go UHT 110ml', 'kategori' => 'Minuman', 'harga' => 3500, 'satuan' => 'kotak'],
            '8992772111015' => ['nama' => 'Pocari Sweat Isotonik 500ml', 'kategori' => 'Minuman', 'harga' => 8000, 'satuan' => 'botol'],
            '8992772111022' => ['nama' => 'Pocari Sweat Isotonik 350ml', 'kategori' => 'Minuman', 'harga' => 6500, 'satuan' => 'botol'],
            '8992772111039' => ['nama' => 'Pocari Sweat Can 330ml', 'kategori' => 'Minuman', 'harga' => 7500, 'satuan' => 'kaleng'],
            '8997035130017' => ['nama' => 'You C1000 Vitamin Orange 140ml', 'kategori' => 'Minuman', 'harga' => 7500, 'satuan' => 'botol'],
            '8997035130024' => ['nama' => 'You C1000 Vitamin Lemon 140ml', 'kategori' => 'Minuman', 'harga' => 7500, 'satuan' => 'botol'],
            '8998838200015' => ['nama' => 'Hydro Coco Original 250ml', 'kategori' => 'Minuman', 'harga' => 7500, 'satuan' => 'kotak'],
            '8991002104044' => ['nama' => 'Good Day Cappuccino 250ml', 'kategori' => 'Minuman', 'harga' => 7500, 'satuan' => 'botol'],
            '8991002104013' => ['nama' => 'Good Day Moccacinno 250ml', 'kategori' => 'Minuman', 'harga' => 7500, 'satuan' => 'botol'],
            '8991002104051' => ['nama' => 'Good Day Tiramisu Bliss 250ml', 'kategori' => 'Minuman', 'harga' => 7500, 'satuan' => 'botol'],
            '8991002101340' => ['nama' => 'Kopi Kapal Api Spesial Mix 24g', 'kategori' => 'Minuman', 'harga' => 1500, 'satuan' => 'sachet'],
            '8993175110013' => ['nama' => 'Luwak White Koffie Sachet', 'kategori' => 'Minuman', 'harga' => 2000, 'satuan' => 'sachet'],
            '8996001401013' => ['nama' => 'Torabika Cappuccino Sachet', 'kategori' => 'Minuman', 'harga' => 2500, 'satuan' => 'sachet'],
            '8992761001011' => ['nama' => 'Coca-Cola Botol 390ml', 'kategori' => 'Minuman', 'harga' => 5500, 'satuan' => 'botol'],
            '8992761002018' => ['nama' => 'Sprite Botol 390ml', 'kategori' => 'Minuman', 'harga' => 5500, 'satuan' => 'botol'],
            '8992761003015' => ['nama' => 'Fanta Strawberry Botol 390ml', 'kategori' => 'Minuman', 'harga' => 5500, 'satuan' => 'botol'],

            // Mie Instan & Makanan
            '0089686180626' => ['nama' => 'Indomie Mi Goreng Spesial 85g', 'kategori' => 'Makanan & Snack', 'harga' => 3500, 'satuan' => 'bungkus'],
            '089686010049' => ['nama' => 'Indomie Mi Goreng Spesial 85g', 'kategori' => 'Makanan & Snack', 'harga' => 3500, 'satuan' => 'bungkus'],
            '8998866200223' => ['nama' => 'Indomie Mi Goreng Spesial 85g', 'kategori' => 'Makanan & Snack', 'harga' => 3500, 'satuan' => 'bungkus'],
            '8998866200216' => ['nama' => 'Indomie Kuah Soto Mie 70g', 'kategori' => 'Makanan & Snack', 'harga' => 3200, 'satuan' => 'bungkus'],
            '8998866200230' => ['nama' => 'Indomie Kuah Kari Ayam 72g', 'kategori' => 'Makanan & Snack', 'harga' => 3500, 'satuan' => 'bungkus'],
            '8998866200247' => ['nama' => 'Indomie Kuah Ayam Bawang 69g', 'kategori' => 'Makanan & Snack', 'harga' => 3200, 'satuan' => 'bungkus'],
            '8998866200254' => ['nama' => 'Indomie Goreng Rendang 91g', 'kategori' => 'Makanan & Snack', 'harga' => 3500, 'satuan' => 'bungkus'],
            '8998866200421' => ['nama' => 'Indomie Goreng Ayam Geprek Hype Abis 85g', 'kategori' => 'Makanan & Snack', 'harga' => 3500, 'satuan' => 'bungkus'],
            '8998888120011' => ['nama' => 'Mie Sedaap Goreng 90g', 'kategori' => 'Makanan & Snack', 'harga' => 3300, 'satuan' => 'bungkus'],
            '8998888120028' => ['nama' => 'Mie Sedaap Kuah Soto 75g', 'kategori' => 'Makanan & Snack', 'harga' => 3200, 'satuan' => 'bungkus'],
            '8998888120035' => ['nama' => 'Mie Sedaap Kuah Ayam Bawang 70g', 'kategori' => 'Makanan & Snack', 'harga' => 3200, 'satuan' => 'bungkus'],
            '8998888120066' => ['nama' => 'Mie Sedaap Korean Spicy Chicken 87g', 'kategori' => 'Makanan & Snack', 'harga' => 3500, 'satuan' => 'bungkus'],
            '8998866200308' => ['nama' => 'Pop Mie Rasa Ayam 75g', 'kategori' => 'Makanan & Snack', 'harga' => 5500, 'satuan' => 'cup'],
            '8998866200315' => ['nama' => 'Pop Mie Rasa Baso 75g', 'kategori' => 'Makanan & Snack', 'harga' => 5500, 'satuan' => 'cup'],
            '8998866200322' => ['nama' => 'Pop Mie Goreng Pedas Gledek 75g', 'kategori' => 'Makanan & Snack', 'harga' => 5500, 'satuan' => 'cup'],

            // Snack, Wafer & Biskuit
            '089686598056' => ['nama' => 'Chitato Sapi Panggang 68g', 'kategori' => 'Makanan & Snack', 'harga' => 11500, 'satuan' => 'bungkus'],
            '089686598050' => ['nama' => 'Chitato Sapi Panggang 68g', 'kategori' => 'Makanan & Snack', 'harga' => 11500, 'satuan' => 'bungkus'],
            '089686598063' => ['nama' => 'Chitato Ayam Bumbu 68g', 'kategori' => 'Makanan & Snack', 'harga' => 11500, 'satuan' => 'bungkus'],
            '0089686732061' => ['nama' => 'Chitato Lite Sapi Panggang 68g', 'kategori' => 'Makanan & Snack', 'harga' => 11500, 'satuan' => 'bungkus'],
            '0089686732078' => ['nama' => 'Chitato Lite Rumput Laut 68g', 'kategori' => 'Makanan & Snack', 'harga' => 11500, 'satuan' => 'bungkus'],
            '8998866602010' => ['nama' => 'Qtela Keripik Singkong Balado 60g', 'kategori' => 'Makanan & Snack', 'harga' => 7000, 'satuan' => 'bungkus'],
            '8998866602027' => ['nama' => 'Qtela Keripik Singkong Original 60g', 'kategori' => 'Makanan & Snack', 'harga' => 7000, 'satuan' => 'bungkus'],
            '089686598018' => ['nama' => 'Chiki Balls Keju 55g', 'kategori' => 'Makanan & Snack', 'harga' => 6000, 'satuan' => 'bungkus'],
            '089686598025' => ['nama' => 'Chiki Balls Cokelat 55g', 'kategori' => 'Makanan & Snack', 'harga' => 6000, 'satuan' => 'bungkus'],
            '089686598032' => ['nama' => 'Chiki Balls Ayam 55g', 'kategori' => 'Makanan & Snack', 'harga' => 6000, 'satuan' => 'bungkus'],
            '8992727003013' => ['nama' => 'Taro Net Seaweed Rumput Laut 36g', 'kategori' => 'Makanan & Snack', 'harga' => 5000, 'satuan' => 'bungkus'],
            '8992727003020' => ['nama' => 'Taro Net Cowboy BBQ 36g', 'kategori' => 'Makanan & Snack', 'harga' => 5000, 'satuan' => 'bungkus'],
            '8992745300019' => ['nama' => 'Kusuka Keripik Singkong Balado 60g', 'kategori' => 'Makanan & Snack', 'harga' => 6500, 'satuan' => 'bungkus'],
            '8992745300026' => ['nama' => 'Kusuka Keripik Singkong Original 60g', 'kategori' => 'Makanan & Snack', 'harga' => 6500, 'satuan' => 'bungkus'],
            '8992727005017' => ['nama' => 'Piatos Sapi Panggang 75g', 'kategori' => 'Makanan & Snack', 'harga' => 10500, 'satuan' => 'bungkus'],
            '8992727005024' => ['nama' => 'Piatos Rumput Laut 75g', 'kategori' => 'Makanan & Snack', 'harga' => 10500, 'satuan' => 'bungkus'],
            '8996001304130' => ['nama' => 'Beng-Beng Wafer Cokelat 20g', 'kategori' => 'Makanan & Snack', 'harga' => 2500, 'satuan' => 'pcs'],
            '8996001304147' => ['nama' => 'Beng-Beng Maxx 32g', 'kategori' => 'Makanan & Snack', 'harga' => 3500, 'satuan' => 'pcs'],
            '8991001100108' => ['nama' => 'SilverQueen Cashew 58g', 'kategori' => 'Makanan & Snack', 'harga' => 16500, 'satuan' => 'pcs'],
            '8991001100115' => ['nama' => 'SilverQueen Almond 58g', 'kategori' => 'Makanan & Snack', 'harga' => 16500, 'satuan' => 'pcs'],
            '8991001100122' => ['nama' => 'SilverQueen Cashew 28g', 'kategori' => 'Makanan & Snack', 'harga' => 9000, 'satuan' => 'pcs'],
            '8996001301016' => ['nama' => 'Roma Biskuit Kelapa 300g', 'kategori' => 'Makanan & Snack', 'harga' => 11000, 'satuan' => 'bungkus'],
            '8996001301047' => ['nama' => 'Roma Sari Gandum 115g', 'kategori' => 'Makanan & Snack', 'harga' => 7500, 'satuan' => 'bungkus'],
            '8996001301054' => ['nama' => 'Roma Malkist Cokelat 120g', 'kategori' => 'Makanan & Snack', 'harga' => 7500, 'satuan' => 'bungkus'],
            '8996001301061' => ['nama' => 'Roma Malkist Keju Manis 120g', 'kategori' => 'Makanan & Snack', 'harga' => 7500, 'satuan' => 'bungkus'],
            '8996001301078' => ['nama' => 'Roma Malkist Crackers 135g', 'kategori' => 'Makanan & Snack', 'harga' => 6500, 'satuan' => 'bungkus'],
            '8996001301085' => ['nama' => 'Roma Malkist Abon 135g', 'kategori' => 'Makanan & Snack', 'harga' => 7500, 'satuan' => 'bungkus'],
            '8992760136013' => ['nama' => 'Oreo Vanilla Biskuit 133g', 'kategori' => 'Makanan & Snack', 'harga' => 10000, 'satuan' => 'bungkus'],
            '8992760136020' => ['nama' => 'Oreo Cokelat Biskuit 133g', 'kategori' => 'Makanan & Snack', 'harga' => 10000, 'satuan' => 'bungkus'],
            '8992760136037' => ['nama' => 'Oreo Strawberry Biskuit 133g', 'kategori' => 'Makanan & Snack', 'harga' => 10000, 'satuan' => 'bungkus'],
            '8993175536417' => ['nama' => 'Nabati Wafer Keju Richeese 50g', 'kategori' => 'Makanan & Snack', 'harga' => 4000, 'satuan' => 'bungkus'],
            '8993175536424' => ['nama' => 'Nabati Wafer Cokelat Richoco 50g', 'kategori' => 'Makanan & Snack', 'harga' => 4000, 'satuan' => 'bungkus'],
            '8992775111012' => ['nama' => 'Chocolatos Wafer Roll 24g', 'kategori' => 'Makanan & Snack', 'harga' => 2000, 'satuan' => 'pcs'],
            '8991002301016' => ['nama' => 'Tango Wafer Cokelat 130g', 'kategori' => 'Makanan & Snack', 'harga' => 8000, 'satuan' => 'bungkus'],
            '8991002301023' => ['nama' => 'Tango Wafer Vanila 130g', 'kategori' => 'Makanan & Snack', 'harga' => 8000, 'satuan' => 'bungkus'],
            '8996001302013' => ['nama' => 'Better Sandwich Biscuit 22g', 'kategori' => 'Makanan & Snack', 'harga' => 2000, 'satuan' => 'pcs'],
            '8996001303010' => ['nama' => 'Choki Choki Chococashew 11g', 'kategori' => 'Makanan & Snack', 'harga' => 1500, 'satuan' => 'pcs'],
            '8998838380014' => ['nama' => 'Sari Roti Roti Tawar Kupas', 'kategori' => 'Makanan & Snack', 'harga' => 15000, 'satuan' => 'bungkus'],
            '8998838380021' => ['nama' => 'Sari Roti Roti Manis Cokelat', 'kategori' => 'Makanan & Snack', 'harga' => 5000, 'satuan' => 'bungkus'],
        ];
    }

    public static function cariMaster(string $barcodeRaw): ?array
    {
        $katalog = self::masterList();
        $clean = ltrim($barcodeRaw, '0');

        foreach ($katalog as $b => $item) {
            $bStr = (string) $b;
            $bClean = ltrim($bStr, '0');
            if ($bStr === $barcodeRaw || ($clean !== '' && $bClean === $clean)) {
                return $item + ['barcode' => $bStr];
            }
            if (strlen($barcodeRaw) >= 8 && strlen($bStr) >= 8) {
                if (str_starts_with($bStr, $barcodeRaw) || str_starts_with($barcodeRaw, $bStr)) {
                    return $item + ['barcode' => $bStr];
                }
            }
        }
        return null;
    }

    public static function cariMasterByName(string $query): ?array
    {
        $q = strtolower(trim($query));
        if ($q === '') return null;

        $katalog = self::masterList();

        // 1. Cocok persis atau mengandung nama
        foreach ($katalog as $b => $item) {
            $namaLower = strtolower($item['nama']);
            if (str_contains($namaLower, $q)) {
                return $item + ['barcode' => (string) $b];
            }
        }

        // 2. Cocok kata per kata (misal 'cleo 220')
        $words = array_filter(explode(' ', $q));
        if (count($words) > 1) {
            foreach ($katalog as $b => $item) {
                $namaLower = strtolower($item['nama']);
                $semuaCocok = true;
                foreach ($words as $w) {
                    if (! str_contains($namaLower, $w)) {
                        $semuaCocok = false;
                        break;
                    }
                }
                if ($semuaCocok) {
                    return $item + ['barcode' => (string) $b];
                }
            }
        }

        return null;
    }

    /**
     * Cari nama produk di katalog publik Open*Facts (gratis, tanpa API key).
     * Dua basis dicari paralel: Food (makanan/minuman) dan Products
     * (perawatan/rumah tangga) — digabung agar produk Indonesia yang
     * belum dikenal berpeluang langsung menampilkan namanya.
     * Timeout pendek agar kasir tidak menunggu lama saat offline / lambat.
     */
    public static function cariOpenFoodFacts(string $barcodeRaw): ?array
    {
        try {
            $res = Http::timeout(4)->connectTimeout(3)
                ->withHeaders(['User-Agent' => 'VOLTIX-POS/1.0'])
                ->get('https://world.openfoodfacts.org/api/v2/product/'.urlencode($barcodeRaw).'.json', [
                    'fields' => 'product_name,brands,quantity,categories_tags',
                ]);
            return self::petakanProdukPublik($res);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Varian non-makanan (sabun, deterjen, dsb.) — bentuk respons sama. */
    public static function cariOpenProductsFacts(string $barcodeRaw): ?array
    {
        try {
            $res = Http::timeout(4)->connectTimeout(3)
                ->withHeaders(['User-Agent' => 'VOLTIX-POS/1.0'])
                ->get('https://world.openproductsfacts.org/api/v2/product/'.urlencode($barcodeRaw).'.json', [
                    'fields' => 'product_name,brands,quantity,categories_tags',
                ]);
            return self::petakanProdukPublik($res);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Cari kedua katalog publik sekaligus (paralel), ambil yang ketemu dulu. */
    public static function cariKatalogPublik(string $barcodeRaw): ?array
    {
        try {
            $hasil = Http::pool(function ($pool) use ($barcodeRaw) {
                $url = fn (string $host) => "https://{$host}/api/v2/product/".urlencode($barcodeRaw).'.json';
                $param = ['fields' => 'product_name,brands,quantity,categories_tags'];
                $head = ['User-Agent' => 'VOLTIX-POS/1.0'];
                return [
                    $pool->timeout(4)->connectTimeout(3)->withHeaders($head)->get($url('world.openfoodfacts.org'), $param),
                    $pool->timeout(4)->connectTimeout(3)->withHeaders($head)->get($url('world.openproductsfacts.org'), $param),
                ];
            });
            // Urutan pool mengikuti urutan selesai, jadi sumber dinormalkan:
            // yang penting bagi kasir hanya "katalog publik" vs "master".
            foreach ($hasil as $res) {
                $item = self::petakanProdukPublik($res);
                if ($item) {
                    $item['sumber'] = 'publik';
                    return $item;
                }
            }
            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function petakanProdukPublik(mixed $res): ?array
    {
        try {
            if (! $res || ! $res->ok()) {
                return null;
            }
            $j = $res->json();
            if (($j['status'] ?? 0) !== 1 || empty($j['product']['product_name'])) {
                return null;
            }
            $p = $j['product'];
            $nama = trim((string) $p['product_name']);
            if (! empty($p['brands'])) {
                $nama .= ' ('.trim((string) $p['brands']).')';
            }

            $kategori = 'Lainnya';
            $tags = array_map('strtolower', (array) ($p['categories_tags'] ?? []));
            $teks = implode(' ', $tags);
            if (str_contains($teks, 'beverage') || str_contains($teks, 'drink')) {
                $kategori = 'Minuman';
            } elseif (str_contains($teks, 'snack') || str_contains($teks, 'food') || str_contains($teks, 'biscuit') || str_contains($teks, 'noodle') || str_contains($teks, 'bread') || str_contains($teks, 'cereal') || str_contains($teks, 'dair') || str_contains($teks, 'chocolate') || str_contains($teks, 'cand')) {
                $kategori = 'Makanan & Snack';
            } elseif (str_contains($teks, 'hygiene') || str_contains($teks, 'clean') || str_contains($teks, 'cosmet') || str_contains($teks, 'care') || str_contains($teks, 'soap') || str_contains($teks, 'detergent') || str_contains($teks, 'household')) {
                $kategori = 'Perawatan & Kebersihan';
            }

            $satuan = 'pcs';
            if (! empty($p['quantity']) && preg_match('/(ml|litre|liter|g|kg|mg)\b/i', (string) $p['quantity'], $m)) {
                $u = strtolower($m[1]);
                $satuan = match ($u) {
                    'ml' => 'ml',
                    'litre', 'liter' => 'liter',
                    'kg' => 'kg',
                    'mg' => 'mg',
                    default => 'g',
                };
            }

            return ['nama' => $nama, 'kategori' => $kategori, 'satuan' => $satuan];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Samakan kategori dengan data yang sudah ada (anti-duplikat):
     * cari yang namanya sama persis abaikan huruf besar-kecil dulu,
     * baru buat bila benar-benar belum ada.
     */
    public static function resolveKategori(string $nama): \App\Models\Kategori
    {
        $nama = trim($nama) !== '' ? trim($nama) : 'Lainnya';
        $ada = \App\Models\Kategori::valid()
            ->whereRaw('LOWER(nama) = ?', [mb_strtolower($nama)])
            ->first();
        return $ada ?? \App\Models\Kategori::create([
            'nama' => $nama,
            'id_kelompok' => null,
            'is_delete' => 0,
        ]);
    }

    /**
     * Susun kandidat pengenalan untuk satu barcode: master lokal dulu,
     * lalu OpenFoodFacts, lalu kandidat kosong untuk isi manual.
     */
    public static function kandidat(string $barcodeRaw): array
    {
        $master = self::cariMaster($barcodeRaw);
        if ($master) {
            return [
                'barcode' => $barcodeRaw,
                'nama' => $master['nama'],
                'kategori' => $master['kategori'],
                'satuan' => $master['satuan'] ?? 'pcs',
                'harga' => (int) $master['harga'],
                'sumber' => 'master',
                'barcode_valid' => self::eanValid($barcodeRaw),
                'produk_indonesia' => str_starts_with(ltrim($barcodeRaw, '0'), '899'),
            ];
        }

        $off = self::cariKatalogPublik($barcodeRaw);

        return [
            'barcode' => $barcodeRaw,
            'nama' => $off['nama'] ?? null,
            'kategori' => $off['kategori'] ?? 'Lainnya',
            'satuan' => $off['satuan'] ?? 'pcs',
            'harga' => null,
            'sumber' => $off ? ($off['sumber'] ?? 'openfoodfacts') : null,
            'barcode_valid' => self::eanValid($barcodeRaw),
            'produk_indonesia' => str_starts_with(ltrim($barcodeRaw, '0'), '899'),
        ];
    }
}
