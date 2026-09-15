<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyimpan tanda tangan pengguna untuk dibubuhkan pada dokumen bukti.
 *
 * Tanda tangan digambar sekali lalu diingat, bukan digambar ulang setiap kali
 * sebuah tahapan dituntaskan. Petugas Gudang dapat menyiapkan puluhan
 * permintaan dalam sehari, dan menuntut goresan baru pada tiap permintaan
 * hanya akan melahirkan tanda tangan yang makin lama makin asal — yang justru
 * melemahkan nilai pembuktiannya.
 *
 * Yang disimpan adalah lintasan berkasnya, bukan gambarnya sendiri. Berkas
 * gambar diletakkan pada cakram `local` yang tidak dapat dijangkau dari web,
 * sebab tanda tangan adalah tiruan tanda tangan basah pegawai: ia hanya perlu
 * dibaca peladen ketika membentuk PDF, dan tidak pernah perlu dapat diunduh
 * siapa pun lewat alamat langsung.
 *
 * Waktu penyimpanan ikut dicatat supaya pertanyaan "sejak kapan tanda tangan
 * ini dipakai" dapat dijawab tanpa menebak dari waktu berkas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('tanda_tangan_path', 255)->nullable()->after('no_hp');
            $table->timestamp('tanda_tangan_at')->nullable()->after('tanda_tangan_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['tanda_tangan_path', 'tanda_tangan_at']);
        });
    }
};
