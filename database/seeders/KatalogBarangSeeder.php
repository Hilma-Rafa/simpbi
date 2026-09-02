<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder data awal katalog barang persediaan.
 *
 * Sumber data: Kartu Kendali Barang Persediaan Sub-Bagian Umum
 * BPS Kota Jakarta Barat Tahun 2025.
 *
 * Nama barang dan satuan telah dibakukan penulisannya, karena pada
 * dokumen sumber ditemukan variasi penulisan untuk maksud yang sama.
 */
class KatalogBarangSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // ---------- KATEGORI ----------
        $kategori = [
            ['kode_akun' => '117111', 'kode_kategori' => '1010301001', 'nama_kategori' => 'Alat Tulis', 'tipe' => 'persediaan'],
            ['kode_akun' => '117111', 'kode_kategori' => '1010301003', 'nama_kategori' => 'Penjepit Kertas', 'tipe' => 'persediaan'],
            ['kode_akun' => '117111', 'kode_kategori' => '1010301006', 'nama_kategori' => 'Ordner Dan Map', 'tipe' => 'persediaan'],
            ['kode_akun' => '117111', 'kode_kategori' => '1010301010', 'nama_kategori' => 'Alat Perekat', 'tipe' => 'persediaan'],
            ['kode_akun' => '117111', 'kode_kategori' => '1010301012', 'nama_kategori' => 'Staples', 'tipe' => 'persediaan'],
            ['kode_akun' => '117111', 'kode_kategori' => '1010301999', 'nama_kategori' => 'Alat Tulis Kantor Lainnya', 'tipe' => 'persediaan'],
            ['kode_akun' => '117111', 'kode_kategori' => '1010302001', 'nama_kategori' => 'Kertas HVS', 'tipe' => 'persediaan'],
            ['kode_akun' => '117111', 'kode_kategori' => '1010302004', 'nama_kategori' => 'Amplop', 'tipe' => 'persediaan'],
            ['kode_akun' => '117111', 'kode_kategori' => '1010302005', 'nama_kategori' => 'Kop Surat', 'tipe' => 'persediaan'],
            ['kode_akun' => '117111', 'kode_kategori' => '1010302999', 'nama_kategori' => 'Kertas Dan Cover Lainnya', 'tipe' => 'persediaan'],
            ['kode_akun' => '117111', 'kode_kategori' => '1010303002', 'nama_kategori' => 'Tinta Cetak', 'tipe' => 'persediaan'],
            ['kode_akun' => '117111', 'kode_kategori' => '1010304004', 'nama_kategori' => 'Tinta/Toner Printer', 'tipe' => 'persediaan'],
            ['kode_akun' => '117111', 'kode_kategori' => '1010304010', 'nama_kategori' => 'Mouse', 'tipe' => 'persediaan'],
            ['kode_akun' => '117111', 'kode_kategori' => '1010310999', 'nama_kategori' => 'Alat Penunjang Kegiatan Kantor Lainnya', 'tipe' => 'persediaan'],
            ['kode_akun' => '117111', 'kode_kategori' => '1010399999', 'nama_kategori' => 'Alat/Bahan Untuk Kegiatan Kantor Lainnya', 'tipe' => 'persediaan'],
        ];

        foreach ($kategori as $k) {
            DB::table('kategori')->updateOrInsert(
                ['kode_kategori' => $k['kode_kategori']],
                $k + ['created_at' => $now, 'updated_at' => $now]
            );
        }

        $petaKategori = DB::table('kategori')->pluck('id', 'kode_kategori');

        // ---------- BARANG ----------
        // [kode_kategori, kode_barang, nama_barang, satuan, stok_fisik]
        $barang = [
            ['1010301001', '000122', 'Binder Clips No. 105', 'Dus', 1],
            ['1010301001', '000123', 'Binder Clips No. 111', 'Dus', 0],
            ['1010301001', '000124', 'Binder Clips No. 155', 'Dus', 8],
            ['1010301001', '000126', 'Binder Clips No. 260', 'Dus', 2],
            ['1010301001', '000289', 'Ballpoint Balliner', 'Lusin', 1],
            ['1010301001', '000291', 'Lakban Bening 2 Inchi', 'Buah', 0],
            ['1010301001', '000292', 'Lakban Bening 1 Inchi', 'Buah', 0],
            ['1010301001', '000293', 'Double Tape 1 Inchi', 'Buah', 7],
            ['1010301001', '000294', 'Binder Clip No. 107 Jakbar', 'Box', 0],
            ['1010301001', '000295', 'Sticky Note Jakbar', 'Pad', 0],
            ['1010301003', '000152', 'Binder Clip No. 260', 'Lusin', 0],
            ['1010301003', '000153', 'Paper Clip No. 1 Joyko', 'Pak', 0],
            ['1010301006', '000197', 'Ordner Bantex', 'Buah', 20],
            ['1010301006', '000214', 'Map Folio Berlogo BPS', 'Lembar', 184],
            ['1010301006', '000215', 'Map Snelhecter Berlogo BPS', 'Lembar', 0],
            ['1010301006', '000217', 'Box Arsip', 'Pcs', 12],
            ['1010301006', '000218', 'Map Lidah', 'Pcs', 217],
            ['1010301010', '000235', 'Lem Stik Kenko', 'Buah', 4],
            ['1010301012', '000010', 'Staples No 50 Max', 'Buah', 0],
            ['1010301012', '000013', 'Staples Hd 10 Max', 'Pcs', 0],
            ['1010301012', '000015', 'Staples Besar Joyko Hd-12 L/24', 'Unit', 0],
            ['1010301012', '000016', 'Remover Joyko', 'Buah', 0],
            ['1010301012', '000017', 'Isi Staples No. 10', 'Buah', 0],
            ['1010301999', '000064', 'Sticky Note', 'Pad', 12],
            ['1010301999', '000065', 'Post It 654 3M', 'Pad', 0],
            ['1010302001', '000335', 'Kertas Hvs 80Gr F4 Po', 'Rim', 0],
            ['1010302001', '000336', 'Kertas A4 Paperone 80 Gr', 'Rim', 35],
            ['1010302001', '000337', 'Kertas F4 Paperone 80 Gr', 'Rim', 4],
            ['1010302001', '000342', 'Kertas A3 Paperone 80 Gr', 'Rim', 0],
            ['1010302001', '000343', 'Kertas A4 Paperone 80 gr JB', 'Rim', 0],
            ['1010302001', '000344', 'Kertas F4 Paperone 80 gr JB', 'Rim', 5],
            ['1010302004', '000308', 'Amplop Coklat Besar Berlogo', 'Pcs', 305],
            ['1010302004', '000317', 'Amplop Coklat Kecil Berlogo', 'Pcs', 385],
            ['1010302005', '000004', 'Kertas Kop A4 Warna', 'Rim', 0],
            ['1010302999', '000008', 'Sticky Notes', 'Pad', 16],
            ['1010303002', '000001', 'Toner 80 A2025', 'Buah', 0],
            ['1010304004', '000582', 'Toner Hp 80A', 'Buah', 1],
            ['1010304004', '000601', 'Tinta Epson 664 Black', 'Buah', 0],
            ['1010304004', '000604', 'Tinta Epson 664 Warna', 'Buah', 9],
            ['1010304004', '000610', 'Tinta Epson 774 Black', 'Buah', 3],
            ['1010304004', '000611', 'Toner Hp 89 A', 'Buah', 0],
            ['1010304004', '000616', 'Tinta Epson T664 (C,M,Y,K)', 'Set', 0],
            ['1010304004', '000623', 'Toner Hp 26A', 'Unit', 2],
            ['1010304004', '000624', 'Tinta Epson 003 Black', 'Buah', 2],
            ['1010304004', '000630', 'Tinta Epson 003 Warna', 'Buah', 6],
            ['1010304004', '000631', 'Tinta Epson 008 Hitam', 'Buah', 3],
            ['1010304004', '000632', 'Tinta Epson 008 Warna', 'Buah', 6],
            ['1010304004', '000639', 'Toner HP 80 A Compatible JB', 'Buah', 0],
            ['1010304010', '000008', 'Mouse B 100 Logitech', 'Buah', 8],
            ['1010304010', '000009', 'Mouse M 170 Logitech', 'Buah', 4],
            ['1010310999', '000003', 'Gunting Sedang Kenko', 'Buah', 0],
            ['1010399999', '000145', 'Kuesioner HP', 'Set', 0],
            ['1010399999', '000259', 'Daftar SP-Palawija', 'Set', 0],
            ['1010399999', '000260', 'Daftar SP-lahan, SP-Alsintan TP, SP Benih', 'Set', 0],
            ['1010399999', '000261', 'Rekapitulasi Kab/Kota SP Tanaman Pangan', 'Set', 0],
            ['1010399999', '000266', 'Kuesioner HP-JA', 'Set', 0],
            ['1010399999', '000267', 'Kuesioner HP-JG', 'Set', 0],
            ['1010399999', '000268', 'Kuesioner HP-JP', 'Set', 0],
            ['1010399999', '000269', 'Kuesioner HP-JR', 'Set', 0],
            ['1010399999', '000270', 'Kuesioner HP-JS', 'Set', 0],
            ['1010399999', '000271', 'Kuesioner HP-JTB', 'Set', 0],
            ['1010399999', '000272', 'Suplemen SHP', 'Set', 0],
            ['1010399999', '000296', 'Surat Pengantar Survei Konstruksi', 'Lembar', 0],
            ['1010399999', '000310', 'Penghapus ST2023', 'Pcs', 0],
            ['1010399999', '000311', 'Clipboard Kayu ST2023', 'Pcs', 0],
            ['1010399999', '000312', 'Pulpen ST2023', 'Pcs', 0],
            ['1010399999', '000314', 'Name tag+Tali ST2023', 'Pcs', 0],
            ['1010399999', '000315', 'Pensil 2B ST2023', 'Pcs', 0],
            ['1010399999', '000318', 'Topi ST2023', 'Pcs', 0],
            ['1010399999', '000325', 'Buku Kode Sakernas', 'Buku', 0],
            ['1010399999', '000326', 'Buku Pedoman Pencacah Sakernas', 'Buku', 0],
            ['1010399999', '000327', 'Buku Pedoman Pengawas Sakernas', 'Buku', 0],
            ['1010399999', '000329', 'Buku Pedoman Pencacah Susenas', 'Buku', 0],
            ['1010399999', '000330', 'Buku Pedoman Pengawas Pencacah', 'Buku', 0],
            ['1010399999', '000331', 'Buku Konsep dan Definisi Susenas', 'Buku', 0],
            ['1010399999', '000414', 'Rekomendasi PU', 'Lembar', 0],
            ['1010399999', '000418', 'Surat Pengantar Survei Tahuna', 'Lembar', 0],
            ['1010399999', '000419', 'Surat Pengantar Survei Tahunan', 'Lembar', 0],
            ['1010399999', '000420', 'Surat Pengantar Survei Tahunan', 'Lembar', 0],
            ['1010399999', '000421', 'Surat Pengantar Survei Tahunan', 'Lembar', 0],
            ['1010399999', '000422', 'Surat Pengantar Survei Triwulanan', 'Lembar', 0],
            ['1010399999', '000446', 'Jakarta Barat Dalam Angka Tahun 2023', 'Buku', 0],
            ['1010399999', '000447', 'PDRB Jakarta Barat Menurut Lapangan', 'Buku', 0],
            ['1010399999', '000448', 'PDRB Jakarta Barat Menurut Pengeluaran', 'Buku', 0],
            ['1010399999', '000451', 'Statistik Kesejahteraan Rakyat Jakarta', 'Buku', 0],
            ['1010399999', '000457', 'Kuesioner VSERUTI24.INTI', 'Set', 0],
            ['1010399999', '000458', 'Kuesioner VSERUTI24.MAK', 'Set', 0],
            ['1010399999', '000464', 'Kuesioner SLK-VALAS', 'Set', 0],
            ['1010399999', '000478', 'Kota Jakarta Barat Dalam Angka 2024', 'Buku', 0],
            ['1010399999', '000481', 'Pisau Cutter Kecil A 300', 'Buah', 3],
            ['1010399999', '000482', 'Kuesioner VSEN25.K', 'Set', 0],
            ['1010399999', '000483', 'Kuesioner VSEN25.KP', 'Set', 0],
            ['1010399999', '000484', 'Kuesioner VSERUTI25.INTI', 'Set', 0],
            ['1010399999', '000485', 'Kuesioner VSEN25.P', 'Set', 0],
            ['1010399999', '000486', 'Kuesioner VSEN25.DSBS', 'Set', 0],
            ['1010399999', '000487', 'Kuesioner VSEN25.DSRT', 'Set', 0],
            ['1010399999', '000488', 'Buku Pedoman Seruti', 'Buku', 0],
            ['1010399999', '000489', 'Kuesioner HP-JLP', 'Set', 0],
            ['1010399999', '000490', 'Kuesioner HPT', 'Set', 0],
            ['1010399999', '000491', 'Kuesioner SLK-KSP', 'Set', 0],
            ['1010399999', '000492', 'Surat Pengantar Survai Captive Power', 'Lembar', 0],
            ['1010399999', '000493', 'Lembar Kerja Survei Tahunan Perusahaan', 'Set', 0],
            ['1010399999', '000494', 'Lembar Kerja Survei Tahunan Perusahaan', 'Set', 0],
            ['1010399999', '000495', 'Lembar Kerja Survei Tahunan Perusahaan', 'Set', 0],
            ['1010399999', '000496', 'Lembar Kerja Survei Captive Power', 'Set', 0],
            ['1010399999', '000497', 'Lembar Kerja Survei Triwulanan', 'Set', 0],
            ['1010399999', '000498', 'Lembar Kerja Survei Tahunan Perusahaan', 'Set', 0],
            ['1010399999', '000499', 'Lembar Kerja Survei Tahunan Perusahaan', 'Set', 0],
            ['1010399999', '000500', 'Lembar Kerja Survei Tahunan Perusahaan', 'Set', 0],
            ['1010399999', '000501', 'Lembar Kerja Survei Perusahaan', 'Set', 0],
            ['1010399999', '000502', 'Lembar Kerja Survei Perusahaan', 'Set', 0],
            ['1010399999', '000503', 'Lembar Kerja Survei Perusahaan', 'Set', 0],
            ['1010399999', '000504', 'Lembar Kerja Survei Perusahaan', 'Set', 0],
            ['1010399999', '000506', 'Surat Pengantar Survei Tahunan', 'Lembar', 0],
            ['1010399999', '000507', 'Surat Pengantar Survei Tahunan', 'Lembar', 0],
            ['1010399999', '000508', 'Kuesioner VSERUTI25.MAK', 'Set', 0],
            ['1010399999', '000509', 'Jakarta Dalam Angka tahun 2024', 'Buku', 0],
            ['1010399999', '000510', 'Daftar SAK.AGS25-AK', 'Set', 0],
            ['1010399999', '000511', 'Kuesioner VSEN25.M', 'Set', 0],
        ];

        foreach ($barang as [$kodeKategori, $kodeBarang, $nama, $satuan, $stok]) {
            if (! isset($petaKategori[$kodeKategori])) {
                continue;
            }

            DB::table('barang_persediaan')->updateOrInsert(
                [
                    'kategori_id' => $petaKategori[$kodeKategori],
                    'kode_barang' => $kodeBarang,
                ],
                [
                    'nama_barang'  => $nama,
                    'satuan'       => $satuan,
                    'stok_fisik'   => $stok,
                    'stok_hold'    => 0,
                    'stok_minimum' => 0,
                    'status_aktif' => true,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]
            );
        }
    }
}