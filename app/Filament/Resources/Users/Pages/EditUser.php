<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\AksiHapusTerlindung;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AksiHapusTerlindung::tunggal(UserResource::ALASAN_TAK_DAPAT_DIHAPUS),
        ];
    }
}
