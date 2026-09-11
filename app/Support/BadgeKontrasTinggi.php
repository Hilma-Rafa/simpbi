<?php

namespace App\Support;

use Filament\Support\Facades\FilamentColor;
use Filament\Support\View\Components\BadgeComponent;
use Filament\Support\View\Components\ColorMaps\ComponentColorMap;

/**
 * Badge dengan kontras teks satu tingkat di atas ambang bawaan Filament.
 *
 * Filament memilih warna teks badge secara otomatis: dari tangga warna yang
 * terdaftar, diambil shade **paling terang yang masih lolos WCAG AA** (rasio
 * 4,5) terhadap latar badge. Cara itu aman menurut standar, tetapi hasilnya
 * selalu berhenti tepat di ambang — teks badge status pada tabel karena itu
 * terbaca tipis, apalagi pada ukuran huruf 12 piksel dan di layar terang.
 *
 * Ambangnya di sini dinaikkan menjadi 6,0: di atas AA, di bawah AAA (7,0).
 * Angka itu dipilih karena tepat menggeser setiap warna satu langkah pada
 * tangga — primary 500 → 600, dan warning, danger, success serta info dari
 * 700 → 800 — tanpa membuat warna gelapnya melompat ke 900 yang mulai
 * terbaca sebagai hitam dan menghilangkan makna warna keadaannya.
 *
 * Ambang dinaikkan, bukan shade-nya ditulis mati, supaya aturannya tetap
 * berlaku bila palet pada AdminPanelProvider suatu saat disetel ulang.
 *
 * Pada mode gelap arah "lebih kontras" berkebalikan: Filament mencari dari
 * shade tergelap, sehingga ambang yang lebih tinggi justru menghasilkan warna
 * yang lebih muda (300 → 100). Itu memang yang dikehendaki.
 */
class BadgeKontrasTinggi extends BadgeComponent
{
    /**
     * Rasio kontras minimum teks badge terhadap latarnya.
     */
    public const RASIO_MINIMUM = 6.0;

    /**
     * @param  array<int, string>  $color
     * @return array<string, int>
     */
    public function getColorMap(array $color): array
    {
        $gray = FilamentColor::getColor('gray');

        // Bentuk pemanggilannya sengaja dijaga sama persis dengan induknya —
        // yang berbeda hanya minRatio — agar perubahan pada Filament mudah
        // dibandingkan ketika pustakanya diperbarui.
        return ComponentColorMap::make($color)
            ->slot(
                'text',
                surface: $color[50],
                minRatio: static::RASIO_MINIMUM,
                fallback: 900,
            )
            ->slot(
                'dark:text',
                surface: $gray[600],
                minRatio: static::RASIO_MINIMUM,
                maxShade: 500,
                shouldStartFromDarkest: true,
                fallback: 200,
            )
            ->get();
    }
}
