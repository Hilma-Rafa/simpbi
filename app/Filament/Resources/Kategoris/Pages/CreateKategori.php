<?php

namespace App\Filament\Resources\Kategoris\Pages;

use App\Filament\Resources\Kategoris\KategoriResource;
use App\Filament\Resources\Kategoris\Schemas\KategoriForm;
use App\Filament\Support\AksiKembali;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

class CreateKategori extends CreateRecord
{
    protected static string $resource = KategoriResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AksiKembali::keDaftar(static::getResource()),
        ];
    }

    /**
     * Jaring pengaman di server, sebab aturan `unique()` pada formulir dapat
     * terlewati oleh dua penyimpanan yang bersamaan persis. Pesannya sama
     * dengan aturan formulir, supaya pengguna tidak melihat galat basis data
     * mentah (pola CreateBarangPersediaan::handleRecordCreation()).
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (UniqueConstraintViolationException) {
            Notification::make()
                ->title(KategoriForm::PESAN_KODE_DIPAKAI)
                ->danger()
                ->send();

            throw new Halt;
        }
    }
}
