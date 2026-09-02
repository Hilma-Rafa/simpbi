<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifikasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('judul', 150);
            $table->text('pesan');
            $table->enum('tipe', ['permintaan', 'mutasi', 'stok']);
            // Satu kejadian dapat menghasilkan dua baris: in_app dan whatsapp,
            // sehingga riwayat pengiriman terlacak per kanal.
            $table->enum('channel', ['in_app', 'whatsapp'])->default('in_app');
            // Hanya relevan untuk channel whatsapp
            $table->enum('status_kirim', ['pending', 'terkirim', 'gagal'])->nullable();
            $table->dateTime('dikirim_at')->nullable();
            $table->text('error_message')->nullable();
            // Penunjuk polimorfik sederhana ke transaksi terkait
            $table->string('referensi_tabel', 50)->nullable();
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->dateTime('dibaca_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'dibaca_at']);
            $table->index(['channel', 'status_kirim']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi');
    }
};
