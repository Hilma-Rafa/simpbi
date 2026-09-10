<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan', function (Blueprint $table) {
            $table->id();
            $table->string('kunci', 80)->unique();
            $table->string('nilai');
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });

        // Batas waktu HOLD per tahap, dalam satuan jam kerja — satu hari kerja
        // berisi delapan jam, pukul 08.00 sampai 16.00 (Instruksi §17). Nilai 8
        // karena itu berarti satu hari kerja penuh untuk setiap tahapan.
        // Disimpan sebagai data, bukan ditulis mati di kode, agar dapat
        // disesuaikan tanpa mengubah program.
        $now = now();
        DB::table('pengaturan')->insert([
            ['kunci' => 'batas_ketua_jam',       'nilai' => '8', 'keterangan' => 'Batas persetujuan Ketua Tim (jam kerja)',      'created_at' => $now, 'updated_at' => $now],
            ['kunci' => 'batas_verifikasi_jam',  'nilai' => '8', 'keterangan' => 'Batas verifikasi stok fisik (jam kerja)',      'created_at' => $now, 'updated_at' => $now],
            ['kunci' => 'batas_kasubbag_jam',    'nilai' => '8', 'keterangan' => 'Batas persetujuan akhir Kasubbag (jam kerja)', 'created_at' => $now, 'updated_at' => $now],
            ['kunci' => 'batas_penyiapan_jam',   'nilai' => '8', 'keterangan' => 'Batas penyiapan barang (jam kerja)',           'created_at' => $now, 'updated_at' => $now],
            ['kunci' => 'batas_pengambilan_jam', 'nilai' => '8', 'keterangan' => 'Batas pengambilan oleh pemohon (jam kerja)',   'created_at' => $now, 'updated_at' => $now],
            ['kunci' => 'wa_aktif',              'nilai' => '0',  'keterangan' => 'Aktifkan notifikasi WhatsApp (1/0)',           'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan');
    }
};
