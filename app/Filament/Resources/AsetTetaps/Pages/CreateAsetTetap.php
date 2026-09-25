<?php

namespace App\Filament\Resources\AsetTetaps\Pages;

use App\Filament\Resources\AsetTetaps\AsetTetapResource;
use App\Filament\Support\AksiKembali;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

class CreateAsetTetap extends CreateRecord
{
    protected static string $resource = AsetTetapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AksiKembali::keDaftar(static::getResource()),
        ];
    }

    /**
     * Jaring pengaman di server, sebab aturan `unique()` pada formulir dapat
     * terlewati oleh dua penyimpanan yang bersamaan persis (lihat pola yang
     * sama pada StokMasuk::simpanBarangBaru()). Tanpa ini, pelanggaran UNIQUE
     * tampil sebagai galat basis data mentah, bukan pesan yang dapat dipahami.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (UniqueConstraintViolationException) {
            Notification::make()
                ->title('NUP sudah dipakai oleh aset lain.')
                ->danger()
                ->send();

            throw new Halt;
        }
    }

    /**
     * Membuka riwayat penempatan pertama aset, bila timnya sudah diisi.
     *
     * Diletakkan pada halaman ini, bukan pada peristiwa `created` model, karena
     * yang perlu diubah memang hanya penambahan lewat antarmuka. Seeder menulis
     * barisnya sendiri lewat query builder, dan aturan bagi perubahan tim
     * penempatan sesudah aset tersimpan belum ditetapkan — keduanya tidak boleh
     * ikut terbawa hanya karena kaitnya dipasang di tempat yang lebih dalam.
     *
     * Penjaga terhadap baris kembar berada di dalam catatPenempatanAwal(),
     * sehingga menyimpan ulang formulir tidak menambah riwayat kedua.
     */
    protected function afterCreate(): void
    {
        $this->record->catatPenempatanAwal();
    }
}
