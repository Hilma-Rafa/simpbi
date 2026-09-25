<?php

namespace App\Filament\Resources\Tims\Pages;

use App\Filament\Resources\Tims\TimResource;
use App\Filament\Support\AksiKembali;
use Filament\Resources\Pages\CreateRecord;

class CreateTim extends CreateRecord
{
    protected static string $resource = TimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AksiKembali::keDaftar(static::getResource()),
        ];
    }
}
