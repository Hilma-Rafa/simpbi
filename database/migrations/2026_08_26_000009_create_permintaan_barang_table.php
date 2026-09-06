<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permintaan_barang', function (Blueprint $table) {
            $table->id();
            $table->string('kode_permintaan', 30)->unique();
            $table->foreignId('tim_pemohon_id')->constrained('tim')->restrictOnDelete();
            $table->foreignId('pengaju_id')->constrained('users')->restrictOnDelete();
            // Diisi manual karena akun Tim bersifat akun bersama satu tim
            $table->string('nama_pemohon', 100);
            $table->string('nip_pemohon', 30)->nullable();
            $table->text('keterangan_keperluan')->nullable();

            $table->enum('status', [
                'menunggu_ketua',
                'menunggu_verifikasi',
                'menunggu_kasubbag',
                'siap_diproses',
                'siap_diambil',
                'menunggu_pengesahan',
                'selesai',
                'ditolak_ketua',
                'ditolak_kasubbag',
                'bermasalah',
                'kedaluwarsa',
            ])->default('menunggu_ketua');

            // Batas waktu tahap berjalan. Dihitung ulang setiap kali status berubah,
            // berdasarkan nilai di tabel pengaturan. Job terjadwal melepas HOLD
            // pada permintaan yang melewati batas ini.
            $table->dateTime('hold_expired_at')->nullable();
            $table->dateTime('hold_released_at')->nullable();

            // Catatan: seluruh tahapan persetujuan dicatat pada tabel
            // riwayat_persetujuan, bukan sebagai kolom pada tabel ini.

            // Tahap 6 - Pengesahan akhir oleh Kasubbag Umum
            $table->foreignId('pengesahan_oleh_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('pengesahan_at')->nullable();

            // QR verifikasi, diseragamkan dengan mekanisme pada BAST mutasi aset
            $table->string('qr_token', 64)->nullable()->unique();
            // Berkas bukti transaksi (PDF) hasil generate sistem, menjadi objek
            // yang diverifikasi melalui kode QR
            $table->string('file_bukti_path')->nullable();

            $table->timestamps();

            $table->index(['status', 'hold_expired_at']);
            $table->index('tim_pemohon_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permintaan_barang');
    }
};
