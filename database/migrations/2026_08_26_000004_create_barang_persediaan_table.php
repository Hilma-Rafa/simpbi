<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barang_persediaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kategori_id')->constrained('kategori')->restrictOnDelete();
            // PENTING: kode barang hanya unik di dalam kategorinya, bukan global.
            // Contoh nyata: kode 000002 dipakai di kategori Staples DAN Isi Staples.
            $table->string('kode_barang', 30);
            $table->string('nama_barang', 150);
            $table->string('satuan', 20);
            $table->unsignedInteger('stok_fisik')->default(0);
            $table->unsignedInteger('stok_hold')->default(0);
            // 0 = tidak dipantau, tidak akan memicu peringatan stok menipis
            $table->unsignedInteger('stok_minimum')->default(0);
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();

            $table->unique(['kategori_id', 'kode_barang']);
            $table->index('nama_barang');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barang_persediaan');
    }
};
