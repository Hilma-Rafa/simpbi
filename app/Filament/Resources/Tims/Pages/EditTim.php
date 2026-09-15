<?php

namespace App\Filament\Resources\Tims\Pages;

use App\Filament\Resources\Tims\TimResource;
use App\Filament\Support\AksiHapusTerlindung;
use Filament\Resources\Pages\EditRecord;

class EditTim extends EditRecord
{
    protected static string $resource = TimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AksiHapusTerlindung::tunggal(TimResource::ALASAN_TAK_DAPAT_DIHAPUS),
        ];
    }
}
