<?php

namespace App\Filament\Widgets\Concerns;

use Illuminate\Support\HtmlString;

/**
 * Judul panel bagan yang sekaligus menjadi tautan ke halamannya.
 *
 * Kepala panel bagan dirakit sendiri oleh Filament dari nilai getHeading(),
 * sehingga anchor-nya tidak dapat ditulis pada berkas Blade seperti pada
 * panel buatan sendiri. Filament menerima Htmlable pada judul, jadi anchor
 * dirangkai di sini agar ketiga bagan memakai gaya sorot yang persis sama
 * dengan panel Perlu Tindakan, dan agar kelasnya cukup diubah di satu tempat
 * bila gaya sorot judul panel berubah.
 */
trait JudulPanelBertaut
{
    /** Kelas sorot judul, disamakan dengan panel Perlu Tindakan. */
    protected const KELAS_JUDUL = 'transition hover:text-primary-600 dark:hover:text-primary-400';

    protected function judulBertaut(string $judul, string $tautan): HtmlString
    {
        // Alamat dan judul tetap dilewatkan e() sebab keduanya dirakit menjadi
        // HTML mentah; judul memang tetapan, tetapi alamatnya berisi parameter
        // penyaring yang dibentuk dari data.
        return new HtmlString(
            '<a href="' . e($tautan) . '" class="' . self::KELAS_JUDUL . '">'
            . e($judul)
            . '</a>'
        );
    }
}
