<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat tindakan pada alur persetujuan permintaan barang.
     *
     * Setiap tahap dicatat sebagai satu baris agar kelompok atribut yang
     * berpola sama tidak disimpan berulang sebagai kolom pada tabel
     * permintaan_barang.
     */
    public function up(): void
    {        Schema::create('riwayat_persetujuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permintaan_id')->constrained('permintaan_barang')->cascadeOnDelete();
            $table->enum('tahap', [
                'pengajuan',
                'ketua_tim',
                'verifikasi',
                'kasubbag',
                'penyiapan',
                'konfirmasi',
                'pengesahan',
            ]);
            $table->foreignId('pelaksana_id')->constrained('users')->restrictOnDelete();
            $table->enum('keputusan', ['setuju', 'tolak', 'selesai']);
            $table->text('catatan')->nullable();
            $table->dateTime('waktu');
            $table->timestamps();

            $table->index(['permintaan_id', 'tahap']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_persetujuan');
    }
};
