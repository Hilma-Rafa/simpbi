<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tim', function (Blueprint $table) {
            $table->id();
            $table->string('nama_tim', 150);
            $table->unsignedBigInteger('ketua_tim_id')->nullable();
            // Disiapkan untuk sinkronisasi data tim kerja dari KiPApp
            // (impor berkas terlebih dahulu, antarmuka API menyusul)
            $table->string('external_id', 50)->nullable();
            $table->dateTime('synced_at')->nullable();
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();

            $table->index('status_aktif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tim');
    }
};
