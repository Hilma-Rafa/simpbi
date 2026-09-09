<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Penyeragaman nomor WhatsApp.
 *
 * Nomor pada berkas sumber maupun yang diketik pengguna di halaman Pengaturan
 * datang dalam bermacam bentuk: `08123456789`, `+62 812-3456-789`, bahkan
 * `=+62812…` ketika berasal dari sel lembar sebar yang menganggap tanda plus
 * di awal sebagai awal rumus. Seluruh gerbang WhatsApp hanya menerima bentuk
 * E.164 tanpa tanda plus, sehingga penyeragaman dikumpulkan di satu tempat
 * agar tidak ditulis ulang berbeda-beda di seeder, job pengiriman, dan
 * penyuntingan profil.
 */
class NomorWhatsApp
{
    /**
     * Mengubah nomor menjadi bentuk E.164 tanpa tanda plus, misalnya
     * `6281234567890`. Mengembalikan null bila nomornya tidak masuk akal.
     */
    public static function normalkan(?string $nomor): ?string
    {
        $angka = preg_replace('/\D+/', '', (string) $nomor) ?? '';

        if ($angka === '') {
            return null;
        }

        // Awalan lokal 0 diganti kode negara Indonesia
        if (Str::startsWith($angka, '0')) {
            $angka = '62' . ltrim($angka, '0');
        }

        if (! Str::startsWith($angka, '62')) {
            $angka = '62' . $angka;
        }

        // Nomor Indonesia yang sah berkisar 10 sampai 15 angka bersama kode negara
        return (strlen($angka) >= 10 && strlen($angka) <= 15) ? $angka : null;
    }
}
