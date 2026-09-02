<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ketidaksesuaian_barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permintaan_id')->constrained('permintaan_barang')->cascadeOnDelete();
            $table->foreignId('detail_id')->nullable()
                  ->constrained('detail_permintaan_barang')->nullOnDelete();
            $table->foreignId('petugas_id')->constrained('users')->restrictOnDelete();
            $table->text('deskripsi');
            // true  = human error, ditukar/dilengkapi di tempat, permintaan lanjut
            // false = tidak dapat diatasi, RELEASE hold, status jadi bermasalah
            $table->boolean('dapat_diatasi');
            $table->text('tindak_lanjut')->nullable();
            $table->enum('status', ['selesai_di_tempat', 'permanen']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ketidaksesuaian_barang');
    }
};
