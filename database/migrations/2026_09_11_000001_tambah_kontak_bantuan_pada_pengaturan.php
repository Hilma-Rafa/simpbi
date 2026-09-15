<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menambahkan nomor kontak bantuan pada tabel pengaturan.
 *
 * Kaki halaman masuk menyuruh pengguna menghubungi Sub-Bagian Umum tanpa
 * memberi tahu ke mana, padahal kalimat itu satu-satunya jalan keluar bagi
 * pengguna yang terkunci — SIMPBI sengaja tidak menyediakan pemulihan kata
 * sandi mandiri karena akun diberikan Administrator dan sebagian dipakai
 * bersama satu tim.
 *
 * Nomornya disimpan sebagai data, bukan ditulis mati pada view, karena dua
 * sebab. Pertama, ini nomor pegawai sungguhan pada halaman yang dapat dibuka
 * siapa saja, sehingga tidak sepatutnya ikut masuk ke riwayat repositori.
 * Kedua, petugas Sub-Bagian Umum dapat berganti tanpa perlu mengubah program.
 *
 * Nilai awalnya sengaja dikosongkan: selama Administrator belum mengisinya,
 * kaki halaman masuk kembali menampilkan kalimat biasa tanpa tautan, bukan
 * tautan yang menuju nomor kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // updateOrInsert, bukan insert, supaya migrasi ini aman dijalankan
        // pada basis data yang barisnya sudah terlanjur ada.
        DB::table('pengaturan')->updateOrInsert(
            ['kunci' => 'kontak_bantuan_wa'],
            [
                'nilai'      => '',
                'keterangan' => 'Nomor WhatsApp Sub-Bagian Umum untuk bantuan masuk',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('pengaturan')->where('kunci', 'kontak_bantuan_wa')->delete();
    }
};
