<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bast_mutasi_aset', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_bast', 50)->unique();
            $table->foreignId('aset_id')->constrained('aset_tetap')->restrictOnDelete();
            $table->foreignId('tim_asal_id')->constrained('tim')->restrictOnDelete();
            $table->foreignId('tim_tujuan_id')->constrained('tim')->restrictOnDelete();
            $table->text('alasan_mutasi');
            $table->string('pihak_penyerah', 100);
            $table->string('pihak_penerima', 100);
            $table->enum('status', [
                'menunggu_pengesahan',
                'menunggu_konfirmasi',
                'selesai_administratif',
            ])->default('menunggu_pengesahan');
            // PDF hasil generate sistem (BAST digital murni, bukan hasil scan TTD basah)
            $table->string('file_bast_path')->nullable();
            $table->foreignId('dibuat_oleh_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('disahkan_oleh_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('disahkan_at')->nullable();
            // Token QR untuk halaman verifikasi keaslian dokumen
            $table->string('qr_token', 64)->nullable()->unique();
            $table->foreignId('dikonfirmasi_oleh_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('dikonfirmasi_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bast_mutasi_aset');
    }
};
