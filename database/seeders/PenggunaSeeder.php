<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PenggunaSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $tim = DB::table('tim')->pluck('id', 'nama_tim');

        $pengguna = [
            ['admin',    'Administrator Sistem', 'admin',          null],
            ['kasubbag', 'Kasubbag Umum',        'kasubbag',       'Sub Bagian Umum'],
            ['gudang',   'Petugas Gudang',       'petugas_gudang', 'Sub Bagian Umum'],
            ['ketua01',  'Ketua Tim Sosial',     'ketua_tim',      'Statistik Sosial'],
            ['tim01',    'Tim Statistik Sosial', 'tim',            'Statistik Sosial'],
        ];

        foreach ($pengguna as [$username, $nama, $role, $namaTim]) {
            DB::table('users')->updateOrInsert(
                ['username' => $username],
                [
                    'name'         => $nama,
                    'email'        => $username . '@bps.go.id',
                    'password'     => Hash::make('password'),
                    'role'         => $role,
                    'tim_id'       => $namaTim ? ($tim[$namaTim] ?? null) : null,
                    'status_aktif' => true,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]
            );
        }
    }
}