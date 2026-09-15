<?php

/**
 * Pengaturan impor data induk.
 */
return [

    /*
     * Kata sandi awal bagi akun yang lahir dari impor massal.
     *
     * Seragam untuk seluruh akun sekali impor, sebab Administrator harus dapat
     * membagikannya sekaligus kepada puluhan pegawai. Keamanannya tidak
     * bersandar pada kerahasiaan nilai ini, melainkan pada umurnya yang
     * pendek: setiap akun hasil impor ditandai wajib mengganti kata sandi, dan
     * sistem menahannya di halaman penggantian sampai ia benar-benar diganti.
     *
     * Nilainya diambil dari berkas lingkungan supaya pemasangan yang berbeda
     * tidak berbagi kata sandi awal yang sama, dan supaya nilainya tidak ikut
     * tersimpan di riwayat repositori.
     */
    'sandi_awal' => env('IMPOR_SANDI_AWAL', 'SimpbiBaru2026'),

];
