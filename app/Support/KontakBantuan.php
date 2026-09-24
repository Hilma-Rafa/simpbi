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
     * Nomor WhatsApp bantuan dalam bentuk seragam (E.164 tanpa tanda plus),
     * atau null bila belum diisi Administrator maupun tidak masuk akal.
     *
     * Satu tempat pembacaan `pengaturan.kontak_bantuan_wa`, dipakai baik oleh
     * tautanWhatsApp() di sini maupun halaman Pusat Bantuan yang perlu nomor
     * mentahnya sendiri untuk memformat tampilan "+62 xxx-xxxx-xxxx".
     */
    public static function nomor(): ?string
    {
        return NomorWhatsApp::normalkan(
            DB::table('pengaturan')->where('kunci', 'kontak_bantuan_wa')->value('nilai')
        );
    }

    /**
     * Tautan WhatsApp menuju Sub-Bagian Umum, atau null bila nomornya belum
     * diisi Administrator maupun tidak masuk akal.
     *
     * Nomornya diseragamkan memakai NomorWhatsApp::normalkan(), yang sudah
     * dipakai job pengiriman dan seeder, sebab wa.me menuntut bentuk yang sama
     * dengan gerbang WhatsApp: E.164 tanpa tanda plus.
     *
     * $pesan boleh diisi ulang oleh pemanggil yang konteksnya berbeda dari
     * "kendala masuk" — Pusat Bantuan memakai ini untuk mengirim pesan
     * pembuka bantuan umum. Pemanggil yang tidak mengisi apa pun (halaman
     * masuk) tetap mendapat pesan bawaan yang sama seperti sebelumnya.
     */
    public static function tautanWhatsApp(
        ?string $pesan = 'Halo, saya mengalami kendala masuk ke SIMPBI dan memerlukan bantuan.'
    ): ?string {
        $nomor = static::nomor();

        if ($nomor === null) {
            return null;
        }

        return 'https://wa.me/' . $nomor . (filled($pesan) ? '?text=' . rawurlencode($pesan) : '');
    }
}
