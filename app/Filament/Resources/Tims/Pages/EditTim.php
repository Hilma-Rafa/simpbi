<?php

namespace App\Filament\Resources\Tims\Pages;

use App\Filament\Resources\Tims\TimResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTim extends EditRecord
{
    protected static string $resource = TimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
