<?php

namespace App\Filament\Resources\Kategoris\Pages;

use App\Filament\Resources\Kategoris\KategoriResource;
use App\Filament\Support\AksiHapusTerlindung;
use Filament\Resources\Pages\EditRecord;

class EditKategori extends EditRecord
{
    protected static string $resource = KategoriResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AksiHapusTerlindung::tunggal(KategoriResource::ALASAN_TAK_DAPAT_DIHAPUS),
        ];
    }
}
