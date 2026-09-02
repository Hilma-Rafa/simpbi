<?php

namespace App\Filament\Resources\BarangPersediaans\Pages;

use App\Filament\Resources\BarangPersediaans\BarangPersediaanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBarangPersediaan extends EditRecord
{
    protected static string $resource = BarangPersediaanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
