<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Buku besar pergerakan stok (stock ledger).
     *
     * Menjadi satu-satunya sumber kebenaran untuk seluruh pergerakan barang,
     * baik masuk (pengadaan, pengembalian) maupun keluar (permintaan barang)
     * dan koreksi hasil stok opname.
     *
     * Tabel ini yang menjadi dasar pembentukan KARTU KENDALI per barang.
     * Rekap bulanan/triwulanan/tahunan TIDAK disimpan sebagai tabel tersendiri,
     * melainkan dihitung dengan agregasi (GROUP BY) dari tabel ini, agar tidak
     * terjadi pengulangan kelompok data (pelanggaran 1NF) dan agar periode
     * pelaporan dapat dibentuk secara bebas.
     */
    public function up(): void
    {
        Schema::create('mutasi_stok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('barang_persediaan')->cascadeOnDelete();
            $table->date('tanggal');
            $table->enum('jenis', ['masuk', 'keluar', 'koreksi']);
            // Bernilai positif untuk penambahan, negatif untuk pengurangan.
            // Sengaja bukan unsigned agar arah pergerakan terbaca dari nilainya.
            $table->integer('jumlah');
            // Saldo barang setelah transaksi ini dicatat. Disimpan agar kartu
            // kendali tidak perlu menghitung ulang saldo berjalan setiap dibuka,
            // sekaligus menjadi jejak audit bila terjadi selisih.
            $table->unsignedInteger('saldo_sesudah');
            // Selaras dengan kolom "Uraian M/K" pada kartu kendali manual.
            // Nilai dibakukan agar tidak terjadi variasi penulisan seperti pada
            // kartu kendali berjalan (Pemakaian / pemakaian / PEMAKAIAN / Pemakian).
            $table->enum('sumber', [
                'pembelian',
                'transfer_masuk',
                'stok_awal',
                'pemakaian',
                'pengembalian',
                'reklasifikasi_aset',
                'stok_opname',
            ])->nullable();
            // Kolom "Nomor Dasar M/K" pada kartu kendali.
            // Untuk transaksi keluar diisi otomatis dari kode permintaan,
            // untuk transaksi masuk diisi nomor dokumen pengadaan.
            $table->string('nomor_dasar', 60)->nullable();
            // Penunjuk ke transaksi asal, misalnya permintaan_barang / id
            $table->string('referensi_tabel', 50)->nullable();
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->text('keterangan')->nullable();
            $table->foreignId('petugas_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // Indeks utama untuk kartu kendali per barang per periode
            $table->index(['barang_id', 'tanggal']);
            $table->index(['tanggal', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutasi_stok');
    }
};
