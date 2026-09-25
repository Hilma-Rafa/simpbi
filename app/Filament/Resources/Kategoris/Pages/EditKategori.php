<?php

namespace App\Filament\Resources\Kategoris\Pages;

use App\Filament\Resources\Kategoris\KategoriResource;
use App\Filament\Resources\Kategoris\Schemas\KategoriForm;
use App\Filament\Support\AksiHapusTerlindung;
use App\Filament\Support\AksiKembali;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

class EditKategori extends EditRecord
{
    protected static string $resource = KategoriResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AksiKembali::keDaftar(static::getResource()),
            AksiHapusTerlindung::tunggal(KategoriResource::ALASAN_TAK_DAPAT_DIHAPUS),
        ];
    }

    /**
     * Jaring pengaman di server; lihat keterangan pada
     * CreateKategori::handleRecordCreation().
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (UniqueConstraintViolationException) {
            Notification::make()
                ->title(KategoriForm::PESAN_KODE_DIPAKAI)
                ->danger()
                ->send();

            throw new Halt;
        }
    }
}
