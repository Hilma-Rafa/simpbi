<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_permintaan_barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permintaan_id')->constrained('permintaan_barang')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang_persediaan')->restrictOnDelete();
            $table->unsignedInteger('jumlah_diminta');
            // Hasil pengecekan fisik oleh Petugas Gudang
            $table->unsignedInteger('jumlah_verif_fisik')->nullable();
            // Istilah disamakan dengan hasil verifikasi pada BPMN P.2.S6
            $table->enum('kondisi_verif', ['tersedia', 'rusak', 'kurang'])->nullable();
            // Jumlah yang ditetapkan Kasubbag, bisa lebih kecil dari yang diminta (parsial)
            $table->unsignedInteger('jumlah_final')->nullable();
            // Catatan per item, misalnya alasan pemenuhan sebagian (partial)
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index('barang_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_permintaan_barang');
    }
};
