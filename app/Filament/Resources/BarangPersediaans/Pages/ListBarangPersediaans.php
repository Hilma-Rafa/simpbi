<?php

namespace App\Filament\Resources\BarangPersediaans\Pages;

use App\Filament\Resources\BarangPersediaans\BarangPersediaanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBarangPersediaans extends ListRecords
{
    protected static string $resource = BarangPersediaanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
