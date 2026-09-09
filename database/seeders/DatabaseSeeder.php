<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Menjalankan seluruh seeder dalam urutan ketergantungan:
     * Tim → Pengguna → Ketua Tim → Katalog barang persediaan → Stok awal → Aset tetap.
     *
     * KetuaTimSeeder berjalan setelah PenggunaSeeder karena melengkapi akun
     * Ketua Tim bawaan dengan data pegawai sebenarnya, dan akan melewati diri
     * sendiri apabila berkas sumbernya tidak tersedia.
     */
    public function run(): void
    {
        $this->call([
            TimSeeder::class,
            PenggunaSeeder::class,
            KetuaTimSeeder::class,
            KatalogBarangSeeder::class,
            // Dijalankan tepat setelah katalog, sebab saldo pembukanya dibaca
            // dari stok fisik yang baru saja diisi seeder tersebut.
            StokAwalSeeder::class,
            AsetTetapSeeder::class,
        ]);
    }
}
