<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menandai notifikasi yang disingkirkan pengguna dari panel lonceng.
 *
 * Dibuat sebagai kolom tersendiri, bukan dengan memakai ulang `dibaca_at`,
 * sebab keduanya menjawab pertanyaan yang berbeda: `dibaca_at` berarti
 * pemberitahuannya sudah sampai ke mata pengguna, sedangkan kolom ini berarti
 * pengguna tidak ingin lagi melihatnya di panel. Menyatukan keduanya akan
 * membuat sekali klik pada sebuah notifikasi — yang memang menandainya
 * terbaca — ikut menghapusnya dari daftar, dan "Tandai semua dibaca"
 * mengosongkan seluruh panel sekaligus.
 *
 * Barisnya sendiri tidak dihapus. Tabel `notifikasi` merangkap rekam jejak
 * pengiriman: halaman Riwayat menampilkannya sebagai salah satu tab dan
 * mengekspornya, sehingga menghapus baris berarti menghilangkan bukti bahwa
 * pemberitahuan itu pernah diterbitkan. Penghapusan lunak pun tidak dipakai
 * karena scope globalnya akan menyembunyikan baris itu dari Riwayat juga.
 *
 * Waktunya yang dicatat, bukan sekadar penanda benar/salah, agar pertanyaan
 * "kapan notifikasi ini disingkirkan" tetap dapat dijawab.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifikasi', function (Blueprint $table) {
            $table->dateTime('disembunyikan_at')->nullable()->after('dibaca_at');

            // Panel lonceng selalu menyaring kepemilikan lalu membuang yang
            // sudah disingkirkan, jadi kedua kolom itu yang diindeks bersama.
            $table->index(['user_id', 'disembunyikan_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notifikasi', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'disembunyikan_at']);
            $table->dropColumn('disembunyikan_at');
        });
    }
};
