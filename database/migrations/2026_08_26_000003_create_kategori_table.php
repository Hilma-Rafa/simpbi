<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kategori', function (Blueprint $table) {
            $table->id();
            // Mengikuti struktur laporan persediaan BPS
            // kode_akun contoh: 117111 (Barang Konsumsi)
            $table->string('kode_akun', 10);
            // kode_kategori contoh: 1010301001 (Alat Tulis)
            $table->string('kode_kategori', 20)->unique();
            $table->string('nama_kategori', 100);
            $table->enum('tipe', ['persediaan', 'aset_tetap']);
            $table->timestamps();

            $table->index('tipe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kategori');
    }
};
