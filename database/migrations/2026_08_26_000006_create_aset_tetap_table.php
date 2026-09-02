<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aset_tetap', function (Blueprint $table) {
            $table->id();
            $table->string('nup', 30)->unique();
            $table->string('nama_aset', 150);
            $table->foreignId('kategori_id')->constrained('kategori')->restrictOnDelete();
            // Posisi terkini. Sedikit redundan terhadap riwayat_penempatan_aset,
            // tapi disengaja agar query katalog aset tidak perlu join.
            $table->foreignId('tim_penempatan_id')->nullable()->constrained('tim')->nullOnDelete();
            $table->enum('kondisi', ['baik', 'rusak_ringan', 'rusak_berat'])->default('baik');
            // Disiapkan untuk sinkronisasi data aset (impor berkas dahulu, API menyusul)
            $table->enum('sumber_data', ['manual', 'impor', 'api'])->default('manual');
            $table->string('external_id', 50)->nullable();
            $table->dateTime('synced_at')->nullable();
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aset_tetap');
    }
};
