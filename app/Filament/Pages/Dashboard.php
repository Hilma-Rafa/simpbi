<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Users\Schemas\UserForm;
use Filament\Pages\Dashboard as DasborBawaan;

/**
 * Dasbor SIMPBI dengan kisi dua belas kolom.
 *
 * Kisi bawaan Filament hanya dua kolom, sehingga tidak dapat menyatakan
 * pembagian lebar satu banding tiga yang dibutuhkan pasangan bagan
 * Distribusi Frekuensi Stok (4 kolom) dan Pola Permintaan (8 kolom).
 * Dua belas kolom dipilih karena habis dibagi dua, tiga, maupun empat,
 * sehingga panel setengah lebar yang sudah ada tetap dapat dinyatakan
 * sebagai enam kolom tanpa mengubah tampilannya.
 *
 * Di bawah breakpoint lg kisi tetap satu kolom, sehingga pada layar kecil
 * seluruh panel tersusun menurun. Ini mengikuti Instruksi §45 yang
 * mengutamakan tata letak sederhana dan stabil.
 */
class Dashboard extends DasborBawaan
{
    /**
     * Satu baris keterangan di bawah judul "Dasbor".
     *
     * Sebelumnya kepala halaman hanya memuat satu kata, sehingga pengguna
     * tidak diberi tahu dasbor siapa yang sedang dibuka, padahal isinya
     * memang berbeda menurut peran (lihat masing-masing widget Ringkasan).
     * Nama perannya diambil dari daftar yang sama dengan formulir Pengguna,
     * supaya istilahnya tidak pernah berbeda antara dua tempat itu.
     */
    public function getSubheading(): ?string
    {
        $peran = UserForm::ROLE_OPTIONS[auth()->user()?->role] ?? null;

        $tanggal = now()->translatedFormat('l, j F Y');

        // Titik tengah ditulis sebagai escape Unicode di dalam petik ganda,
        // bukan sebagai karakter mentah, supaya berkas ini tetap aman disunting
        // oleh penyunting yang pengkodeannya bukan UTF-8.
        return $peran ? $peran . " \u{00B7} " . $tanggal : $tanggal;
    }

    /**
     * @return int|array<string,?int>
     */
    public function getColumns(): int|array
    {
        return ['default' => 1, 'lg' => 12];
    }
}
