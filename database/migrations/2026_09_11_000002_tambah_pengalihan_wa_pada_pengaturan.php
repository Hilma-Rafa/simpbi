<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menambahkan nomor pengalihan notifikasi WhatsApp pada tabel pengaturan.
 *
 * Penerima notifikasi ditentukan dari perannya, lalu nomornya diambil dari
 * akun masing-masing. Perilaku itu benar untuk pemakaian sehari-hari, tetapi
 * menyulitkan ketika sistem diperagakan: mencoba satu alur permintaan berarti
 * mengirim pesan sungguhan ke nomor Ketua Tim yang sebenarnya, padahal yang
 * sedang berjalan hanyalah peragaan.
 *
 * Selama kunci ini terisi, seluruh notifikasi dibelokkan ke satu nomor itu.
 * Cara ini dipilih ketimbang menyunting nomor pada akun pegawai, sebab nomor
 * aslinya tidak perlu disentuh sama sekali sehingga tidak ada yang harus
 * dikembalikan setelah peragaan selesai — cukup dikosongkan kembali.
 *
 * Nilai awalnya kosong: pemasangan yang tidak pernah mengisinya berperilaku
 * persis seperti sebelum migrasi ini ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // updateOrInsert, mengikuti migrasi kontak bantuan, supaya aman
        // dijalankan pada basis data yang barisnya sudah terlanjur ada.
        DB::table('pengaturan')->updateOrInsert(
            ['kunci' => 'wa_alihkan_ke'],
            [
                'nilai'      => '',
                'keterangan' => 'Bila terisi, seluruh notifikasi WhatsApp dialihkan ke nomor ini',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('pengaturan')->where('kunci', 'wa_alihkan_ke')->delete();
    }
};
