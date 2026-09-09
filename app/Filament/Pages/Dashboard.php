<?php

namespace App\Filament\Pages;

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
     * @return int|array<string,?int>
     */
    public function getColumns(): int|array
    {
        return ['default' => 1, 'lg' => 12];
    }
}
