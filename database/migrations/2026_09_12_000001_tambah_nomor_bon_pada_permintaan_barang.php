<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor bon pengeluaran untuk kolom "Nomor Dasar M/K" pada kartu kendali.
 *
 * Sub-Bagian Umum menomori pengeluaran barang dengan urutan berjalan sendiri —
 * 035, 037, 038 — bukan dengan kode permintaan. Nomor itulah yang selama ini
 * tertulis pada kartu kendali manual, sehingga kartu terbitan SIMPBI harus
 * memakai penomoran yang sama agar dapat disandingkan dengan arsip lama.
 *
 * Nomornya melekat pada permintaan, bukan pada tiap baris buku besar stok,
 * sebab satu permintaan yang memuat lima jenis barang adalah satu bon: nomor
 * yang sama muncul pada kelima kartu kendali barang tersebut. Itu pula yang
 * terbaca pada berkas Sub-Bagian Umum, di mana satu nomor tampak berulang di
 * beberapa lembar.
 *
 * Tahunnya disimpan terpisah karena penomoran dimulai ulang setiap tahun,
 * sehingga "035" saja tidak cukup untuk menunjuk satu bon secara pasti.
 * Keduanya dijadikan indeks unik agar dua bon tidak mungkin bernomor sama.
 *
 * Bernilai null selama permintaan belum mengeluarkan barang. Permintaan yang
 * ditolak, kedaluwarsa, atau bermasalah tidak pernah mendapat nomor — bon
 * hanya lahir ketika barang benar-benar keluar dari gudang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permintaan_barang', function (Blueprint $table) {
            $table->string('nomor_bon', 10)->nullable()->after('kode_permintaan');
            $table->unsignedSmallInteger('tahun_bon')->nullable()->after('nomor_bon');

            $table->unique(['tahun_bon', 'nomor_bon']);
        });
    }

    public function down(): void
    {
        Schema::table('permintaan_barang', function (Blueprint $table) {
            $table->dropUnique(['tahun_bon', 'nomor_bon']);
            $table->dropColumn(['nomor_bon', 'tahun_bon']);
        });
    }
};
