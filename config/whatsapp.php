<?php

/**
 * Pengaturan kanal notifikasi WhatsApp (UC-23).
 *
 * Penyalaan kanalnya sendiri TIDAK diatur di berkas ini, melainkan pada tabel
 * `pengaturan` kunci `wa_aktif` yang dapat diubah Admin lewat halaman
 * Pengaturan tanpa menyentuh kode. Berkas ini hanya menentukan gerbang mana
 * yang dipakai dan kredensialnya, sebab hal itu menyangkut pemasangan di
 * peladen, bukan kebijakan yang diputuskan pengguna.
 */
return [

    /*
     * Pelaksana pengiriman yang dipakai.
     *
     * catat    : hanya menulis pesan ke berkas log, tanpa mengirim apa pun.
     *            Bawaan, agar sistem yang baru dipasang tidak pernah tidak
     *            sengaja mengirim pesan ke nomor pegawai sungguhan.
     *
     * Pelaksana untuk gerbang sebenarnya didaftarkan pada
     * App\Providers\AppServiceProvider ketika gerbangnya sudah dipilih.
     */
    'driver' => env('WHATSAPP_DRIVER', 'catat'),

    /*
     * Jeda paling singkat antar pesan, dalam detik.
     *
     * Gerbang yang memakai klien WhatsApp tidak resmi rawan dianggap sebagai
     * pengirim massal bila pesan dilepas serentak, sehingga antrean diberi
     * jarak. Bernilai nol untuk jalur resmi yang memang tidak membatasi.
     */
    'jeda_detik' => (int) env('WHATSAPP_JEDA_DETIK', 5),

    /*
     * Banyaknya percobaan pengiriman sebelum notifikasi ditandai gagal.
     */
    'percobaan' => (int) env('WHATSAPP_PERCOBAAN', 3),

    /* Saluran log tempat riwayat pengiriman dicatat. */
    'log_channel' => env('WHATSAPP_LOG_CHANNEL', 'stack'),

    /*
     * Gerbang OpenWA yang dipasang sendiri.
     *
     * Nilainya kosong secara bawaan supaya pemasangan yang belum menyiapkan
     * gerbang tidak diam-diam menganggap dirinya siap mengirim: pelaksananya
     * akan melaporkan diri belum terkonfigurasi, dan notifikasi tercatat gagal
     * dengan alasan yang jelas alih-alih hilang tanpa jejak.
     */
    /*
     * Layanan Fonnte.
     *
     * Gerbangnya berjalan di peladen Fonnte, sehingga tidak ada apa pun yang
     * perlu dipasang di sisi SIMPBI — cukup token dari dasbor mereka.
     */
    'fonnte' => [
        // Token perangkat dari dasbor Fonnte, dikirim apa adanya pada tajuk
        // Authorization tanpa awalan "Bearer".
        'token'       => env('WHATSAPP_FONNTE_TOKEN'),
        // Batas waktu satu panggilan, dalam detik
        'batas_detik' => (int) env('WHATSAPP_FONNTE_BATAS_DETIK', 15),
    ],

    'openwa' => [
        // Alamat pangkal gerbang, tanpa /api. Contoh: http://127.0.0.1:2785
        'alamat'   => env('WHATSAPP_OPENWA_ALAMAT'),
        // Nama sesi yang sudah dipindai kode QR-nya pada dasbor OpenWA
        'sesi'     => env('WHATSAPP_OPENWA_SESI', 'simpbi'),
        // Kunci API dari dasbor OpenWA, dikirim pada tajuk X-API-Key
        'kunci_api' => env('WHATSAPP_OPENWA_KUNCI'),
        // Batas waktu satu panggilan, dalam detik
        'batas_detik' => (int) env('WHATSAPP_OPENWA_BATAS_DETIK', 15),
    ],

];
