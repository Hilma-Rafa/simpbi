<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data contoh aset tetap kategori Peralatan dan Mesin beserta riwayat
 * penempatan awalnya, agar alur BAST mutasi aset (UC-16/17/18) dapat
 * didemonstrasikan. Setiap aset memperoleh satu baris penempatan aktif
 * (jenis penempatan_awal) sehingga jejak penempatan lengkap sejak awal.
 */
class AsetTetapSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Kategori aset tetap (dalam scope: Peralatan dan Mesin).
        $kategoriId = DB::table('kategori')->where('kode_kategori', 'PM')->value('id');
        if (! $kategoriId) {
            $kategoriId = DB::table('kategori')->insertGetId([
                'kode_akun'     => '1.3.2',
                'kode_kategori' => 'PM',
                'nama_kategori' => 'Peralatan dan Mesin',
                'tipe'          => 'aset_tetap',
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        $tim = DB::table('tim')->pluck('id', 'nama_tim');

        // [nama aset, kondisi, unit kerja penempatan]
        $aset = [
            ['Laptop Lenovo ThinkPad E14',        'baik',         'Statistik Sosial'],
            ['Laptop HP EliteBook 840',           'baik',         'Statistik Sosial'],
            ['PC Desktop Dell OptiPlex 3090',     'baik',         'Sub Bagian Umum'],
            ['Printer Epson EcoTank L3210',       'baik',         'Sub Bagian Umum'],
            ['Printer HP LaserJet Pro M404',      'baik',         'Statistik Pertanian dan Industri'],
            ['Scanner Canon imageFORMULA',        'baik',         'Statistik Distribusi'],
            ['Proyektor Epson EB-X06',            'baik',         'Sub Bagian Umum'],
            ['AC Split Daikin 1 PK',              'baik',         'Statistik Pertambangan, Energi dan Konstruksi (PEK)'],
            ['UPS APC 1000VA',                    'baik',         'Sub Bagian Umum'],
            ['Laptop Asus VivoBook 14',           'rusak_ringan', 'Neraca Wilayah dan Analisis Statistik (Nerwilis)'],
            ['PC Desktop HP ProDesk 400',         'baik',         'Integrasi Pengolahan dan Diseminasi Statistik (IPDS)'],
            ['Printer Brother DCP-T720DW',        'baik',         'Pembinaan Statistik Sektoral (PSS)'],
        ];

        $urut = 1;
        foreach ($aset as [$nama, $kondisi, $namaTim]) {
            $timId = $tim[$namaTim] ?? null;
            $nup = sprintf('3.10.01.%05d', $urut);

            // Hindari duplikasi bila seeder dijalankan ulang.
            if (DB::table('aset_tetap')->where('nup', $nup)->exists()) {
                $urut++;
                continue;
            }

            $asetId = DB::table('aset_tetap')->insertGetId([
                'nup'               => $nup,
                'nama_aset'         => $nama,
                'kategori_id'       => $kategoriId,
                'tim_penempatan_id' => $timId,
                'kondisi'           => $kondisi,
                'sumber_data'       => 'manual',
                'status_aktif'      => true,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);

            // Riwayat penempatan awal (aktif: tanggal_selesai null).
            DB::table('riwayat_penempatan_aset')->insert([
                'aset_id'         => $asetId,
                'tim_id'          => $timId,
                'tanggal_mulai'   => $now->copy()->subMonths(6)->toDateString(),
                'tanggal_selesai' => null,
                'jenis'           => 'penempatan_awal',
                'bast_id'         => null,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);

            $urut++;
        }
    }
}
