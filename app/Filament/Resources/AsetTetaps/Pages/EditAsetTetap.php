<?php

namespace App\Filament\Resources\AsetTetaps\Pages;

use App\Filament\Resources\AsetTetaps\AsetTetapResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAsetTetap extends EditRecord
{
    protected static string $resource = AsetTetapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
