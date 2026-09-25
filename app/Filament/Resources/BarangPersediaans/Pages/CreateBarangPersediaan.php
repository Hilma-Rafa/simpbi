<?php

namespace App\Filament\Resources\BarangPersediaans\Pages;

use App\Filament\Resources\BarangPersediaans\BarangPersediaanResource;
use App\Filament\Support\AksiKembali;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

class CreateBarangPersediaan extends CreateRecord
{
    protected static string $resource = BarangPersediaanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AksiKembali::keDaftar(static::getResource()),
        ];
    }

    /**
     * Jaring pengaman di server, sebab aturan `unique()` pada formulir dapat
     * terlewati oleh dua penyimpanan yang bersamaan persis. Pesannya sama
     * dengan aturan formulir dan dialog Barang Baru di Stok Masuk, supaya
     * pengguna tidak melihat galat basis data mentah (pola yang sama dengan
     * CreateAsetTetap::handleRecordCreation()).
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (UniqueConstraintViolationException) {
            Notification::make()
                ->title('Kode barang sudah dipakai pada kategori ini.')
                ->danger()
                ->send();

            throw new Halt;
        }
    }
}
