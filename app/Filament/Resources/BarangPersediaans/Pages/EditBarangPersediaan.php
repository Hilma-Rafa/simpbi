<?php

namespace App\Filament\Resources\BarangPersediaans\Pages;

use App\Filament\Resources\BarangPersediaans\BarangPersediaanResource;
use App\Filament\Support\AksiHapusTerlindung;
use Filament\Resources\Pages\EditRecord;

class EditBarangPersediaan extends EditRecord
{
    protected static string $resource = BarangPersediaanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AksiHapusTerlindung::tunggal(BarangPersediaanResource::ALASAN_TAK_DAPAT_DIHAPUS),
        ];
    }
}
