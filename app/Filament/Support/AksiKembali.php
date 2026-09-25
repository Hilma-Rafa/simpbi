<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Enums\IconPosition;

/**
 * Tombol "Kembali" di kepala halaman Buat dan Ubah data induk.
 *
 * Menuju halaman daftar resource-nya secara langsung, bukan riwayat peramban
 * seperti tombol Batal bawaan Filament. Sesudah formulir disimpan, "mundur
 * satu langkah" kerap berarti kembali ke formulir yang sama; alamat daftar
 * yang tetap membuat tujuan tombol ini selalu dapat diramalkan. Penyaring
 * daftar sengaja tidak dibawa (keputusan pemilik G-9).
 *
 * Abu-abu bergaris seperti tombol sekunder lain (Impor, Riwayat), agar tidak
 * bersaing dengan tombol Simpan. Dikumpulkan di sini seperti AksiUbah dan
 * AksiHapusTerlindung, supaya kesepuluh halaman memakai bentuk yang persis
 * sama.
 *
 * Tidak dipasang lewat trait yang menimpa getHeaderActions(): halaman Ubah
 * sudah punya metode itu sendiri, dan metode kelas selalu menang atas metode
 * trait — tombolnya akan hilang diam-diam tanpa satu pun galat.
 */
class AksiKembali
{
    /** @param  class-string<Resource>  $resource */
    public static function keDaftar(string $resource): Action
    {
        return Action::make('kembali')
            ->label('Kembali')
            ->icon('heroicon-m-arrow-left')
            ->iconPosition(IconPosition::Before)
            ->color('gray')
            ->button()
            ->outlined()
            ->url($resource::getUrl('index'));
    }
}
