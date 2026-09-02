<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_penempatan_aset', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aset_id')->constrained('aset_tetap')->cascadeOnDelete();
            $table->foreignId('tim_id')->constrained('tim')->restrictOnDelete();
            $table->date('tanggal_mulai');
            // NULL menandakan penempatan yang sedang aktif
            $table->date('tanggal_selesai')->nullable();
            $table->enum('jenis', ['penempatan_awal', 'mutasi']);
            $table->foreignId('bast_id')->nullable()->constrained('bast_mutasi_aset')->nullOnDelete();
            $table->timestamps();

            $table->index(['aset_id', 'tanggal_selesai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_penempatan_aset');
    }
};
