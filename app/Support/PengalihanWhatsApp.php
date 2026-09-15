<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Pengalihan notifikasi WhatsApp ke satu nomor, untuk keperluan peragaan.
 *
 * Dikumpulkan di satu tempat karena dibaca dari dua sisi yang berjauhan: job
 * pengiriman memakainya untuk menentukan nomor tujuan, sedangkan halaman
 * Pengaturan memakainya untuk memberi tahu Administrator bahwa pengalihan
 * sedang menyala. Keduanya harus membaca aturan yang sama persis, sebab
 * keterangan yang tidak sejalan dengan perilaku pengiriman justru berbahaya:
 * Administrator bisa mengira pesan mengalir normal padahal sedang dibelokkan.
 */
class PengalihanWhatsApp
{
    /**
     * Nomor tujuan pengalihan dalam bentuk seragam, atau null bila pengalihan
     * sedang mati — yaitu ketika kuncinya kosong atau isinya bukan nomor yang
     * masuk akal.
     *
     * Isi yang tidak masuk akal sengaja diperlakukan sebagai "mati", bukan
     * sebagai galat, supaya salah ketik tidak pernah berakibat notifikasi
     * berhenti terkirim sama sekali. Yang terjadi hanyalah pesan kembali
     * mengalir ke penerima sebenarnya.
     */
    public static function nomor(): ?string
    {
        return NomorWhatsApp::normalkan(
            DB::table('pengaturan')->where('kunci', 'wa_alihkan_ke')->value('nilai')
        );
    }

    /** Apakah pengalihan sedang menyala. */
    public static function menyala(): bool
    {
        return static::nomor() !== null;
    }
}
