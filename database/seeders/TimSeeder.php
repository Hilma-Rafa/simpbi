<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder data tim kerja struktural BPS Kota Jakarta Barat.
 * Sumber: Tim_Kerja_2026.xlsx
 */
class TimSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $tim = [
            'Sub Bagian Umum',
            'Statistik Sosial',
            'Statistik Pertanian dan Industri',
            'Statistik Pertambangan, Energi dan Konstruksi (PEK)',
            'Statistik Distribusi',
            'Neraca Wilayah dan Analisis Statistik (Nerwilis)',
            'Integrasi Pengolahan dan Diseminasi Statistik (IPDS)',
            'Pembinaan Statistik Sektoral (PSS)',
        ];

        foreach ($tim as $nama) {
            DB::table('tim')->updateOrInsert(
                ['nama_tim' => $nama],
                [
                    'status_aktif' => true,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]
            );
        }
    }
}