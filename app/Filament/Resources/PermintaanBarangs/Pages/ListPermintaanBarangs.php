<?php

namespace App\Filament\Resources\PermintaanBarangs\Pages;

use App\Filament\Resources\PermintaanBarangs\PermintaanBarangResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPermintaanBarangs extends ListRecords
{
    protected static string $resource = PermintaanBarangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
