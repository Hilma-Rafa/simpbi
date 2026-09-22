<?php

/**
 * Pengaturan halaman Pusat Bantuan (panduan penggunaan dan kontak
 * Sub-Bagian Umum).
 *
 * Nomor WhatsApp bantuan TIDAK disimpan di sini — nomor itu sudah punya
 * rumah sendiri di tabel `pengaturan` kunci `kontak_bantuan_wa`, diatur
 * Admin lewat halaman Pengaturan Sistem, dan dibaca ulang lewat
 * App\Support\KontakBantuan (sumber yang sama dipakai halaman masuk).
 * Menduplikasinya di sini akan membuat dua tempat yang bisa berbeda nilai.
 */
return [

    /* Alamat surel Sub-Bagian Umum untuk pertanyaan/laporan yang lebih rinci. */
    'email' => 'sub-bagianumum@bps.go.id',

    /* Zona waktu acuan jam layanan. */
    'zona_waktu' => 'Asia/Jakarta',

    /*
     * Jam layanan Sub-Bagian Umum per hari.
     *
     * Format 24 jam "H:i". Nilai null berarti libur pada hari itu. Baris
     * tampilan pada halaman Pusat Bantuan dan indikator status layanan
     * dibangun dari struktur ini, bukan ditulis mati pada templat.
     */
    'jam_layanan' => [
        'senin'  => ['buka' => '08:00', 'tutup' => '16:00'],
        'selasa' => ['buka' => '08:00', 'tutup' => '16:00'],
        'rabu'   => ['buka' => '08:00', 'tutup' => '16:00'],
        'kamis'  => ['buka' => '08:00', 'tutup' => '16:00'],
        'jumat'  => ['buka' => '08:00', 'tutup' => '16:30'],
        'sabtu'  => null,
        'minggu' => null,
    ],

    /* Catatan waktu balas yang ditampilkan pada kartu Jam Operasional. */
    'catatan_balasan' => 'Balasan paling lambat 1 hari kerja lewat email',

    /*
     * Berkas panduan penggunaan.
     *
     * Disk 'local' (storage/app/private) — bukan 'public' — supaya berkas
     * ini tidak dapat diakses lewat tautan langsung tanpa melalui rute
     * unduh yang mensyaratkan sesi masuk. Pemilik sistem yang menempatkan
     * berkasnya; bila belum ada, halaman menampilkan keadaan kosong.
     */
    'panduan' => [
        'disk'          => 'local',
        'path'          => 'panduan/Panduan-Penggunaan-SIMPBI.pdf',
        'nama_tampilan' => 'Panduan Penggunaan SIMPBI',
    ],

];
