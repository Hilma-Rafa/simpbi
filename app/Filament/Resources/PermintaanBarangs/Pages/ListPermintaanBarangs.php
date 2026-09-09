<?php

namespace App\Filament\Resources\PermintaanBarangs\Pages;

use App\Filament\Pages\Riwayat;
use App\Filament\Resources\PermintaanBarangs\PermintaanBarangResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\IconPosition;

class ListPermintaanBarangs extends ListRecords
{
    protected static string $resource = PermintaanBarangResource::class;

    // Penyaringan dari dasbor ditangani oleh penyaring `kode_permintaan`
    // yang didaftarkan pada PermintaanBarangResource::table() melalui
    // parameter URL `filters`, sehingga pengguna dapat melihat dan
    // menghapusnya sendiri.

    protected function getHeaderActions(): array
    {
        return [
            // Daftar ini hanya memuat permintaan berjalan; pintasan berikut
            // membuka halaman Riwayat dengan jenis Permintaan Barang terpilih.
            Action::make('riwayat')
                ->label('Riwayat')
                ->icon('heroicon-m-archive-box')
                ->iconPosition(IconPosition::Before)
                ->color('gray')
                ->outlined()
                ->tooltip('Lihat permintaan yang sudah selesai, ditolak, atau kedaluwarsa')
                ->url(Riwayat::getUrl(['jenis' => 'permintaan']))
                ->visible(fn () => Riwayat::canAccess()),
        ];
    }
}
