<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\IconPosition;

/**
 * Gaya seragam tombol pengunduhan: bergaris warna utama, ikon unduh di depan
 * label.
 *
 * Asalnya tombol "Unduh Bukti" pada permintaan barang, yang lebih dulu
 * menetapkan bentuk ini. Tombol unduhan lain sempat menyalin nilainya satu per
 * satu (Unduh BAST) atau memakai gaya sendiri — tautan abu-abu pada Unduh
 * Template, tombol terisi pada Ekspor — sehingga tombol yang maknanya sama
 * tampil berbeda-beda. Dengan satu definisi, perubahan gaya kelak cukup
 * dilakukan di sini (keputusan pemilik G-8).
 *
 * Yang diatur hanya rupa tombolnya. Isi berkas yang diunduh tidak tersentuh
 * sama sekali: label, tooltip, URL, dan aksinya tetap ditulis pemakainya.
 *
 * Menerima ActionGroup juga, karena tombol Ekspor pada Riwayat adalah grup
 * pilihan format; keduanya mengenal metode gaya yang sama.
 *
 * Satu-satunya salinan yang tidak dapat memakai kelas ini adalah tombol
 * "Unduh Panduan" di resources/views/filament/pages/pusat-bantuan.blade.php,
 * sebab ia komponen Blade, bukan objek aksi. Nilainya di sana disalin dari
 * sini dan diberi komentar penunjuk.
 */
class GayaUnduh
{
    public const IKON = 'heroicon-m-arrow-down-tray';

    public static function terapkan(Action|ActionGroup $aksi): Action|ActionGroup
    {
        return $aksi
            ->icon(self::IKON)
            ->iconPosition(IconPosition::Before)
            ->color('primary')
            ->button()
            ->outlined();
    }
}
