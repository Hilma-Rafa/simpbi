<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Kontak bantuan Sub-Bagian Umum untuk pengguna yang gagal masuk.
 *
 * Dikumpulkan di satu tempat karena nomornya dibaca dari luar panel Filament —
 * halaman masuk belum memiliki pengguna terautentikasi, sehingga tidak dapat
 * menumpang layanan mana pun yang bergantung pada sesi.
 */
class KontakBantuan
{
    /**
     * Tautan WhatsApp menuju Sub-Bagian Umum, atau null bila nomornya belum
     * diisi Administrator maupun tidak masuk akal.
     *
     * Nomornya diseragamkan memakai NomorWhatsApp::normalkan(), yang sudah
     * dipakai job pengiriman dan seeder, sebab wa.me menuntut bentuk yang sama
     * dengan gerbang WhatsApp: E.164 tanpa tanda plus.
     */
    public static function tautanWhatsApp(): ?string
    {
        $nomor = NomorWhatsApp::normalkan(
            DB::table('pengaturan')->where('kunci', 'kontak_bantuan_wa')->value('nilai')
        );

        if ($nomor === null) {
            return null;
        }

        // Pesan awal diisikan supaya petugas langsung tahu keperluannya tanpa
        // perlu bertanya balik, dan pengguna yang sedang panik tidak perlu
        // menyusun kalimat sendiri.
        return 'https://wa.me/' . $nomor . '?text=' . rawurlencode(
            'Halo, saya mengalami kendala masuk ke SIMPBI dan memerlukan bantuan.'
        );
    }
}
