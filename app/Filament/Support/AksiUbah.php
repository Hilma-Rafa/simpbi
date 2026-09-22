<?php

namespace App\Filament\Support;

use Filament\Actions\EditAction;
use Filament\Support\Enums\IconPosition;

/**
 * Tombol "Ubah" pada baris tabel data induk.
 *
 * Gayanya tidak dibuat sendiri melainkan meniru tombol aksi utama yang sudah
 * ada, yaitu "Unduh Bukti" pada permintaan barang: tombol bergaris berwarna
 * merek dengan ikon di depan label. Sebelumnya tiap tabel memakai
 * EditAction::make() polos, yang tampil sebagai tautan kelabu dan terbaca
 * berbeda sendiri di antara tombol lain pada sistem.
 *
 * Dikumpulkan di satu tempat, seperti AksiHapusTerlindung dan AksiImpor,
 * supaya kelima tabel data induk (Barang Persediaan, Aset Tetap, Kategori,
 * Tim Kerja, Pengguna) memakai treatment yang persis sama dan tidak
 * menyimpang satu sama lain bila gayanya kelak berubah.
 */
class AksiUbah
{
    public static function buat(): EditAction
    {
        return EditAction::make()
            ->icon('heroicon-m-pencil-square')
            ->iconPosition(IconPosition::Before)
            ->color('primary')
            ->button()
            ->outlined();
    }
}
