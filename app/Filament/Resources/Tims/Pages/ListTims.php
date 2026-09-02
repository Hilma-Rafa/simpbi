<?php

namespace App\Filament\Resources\Tims\Pages;

use App\Filament\Resources\Tims\TimResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTims extends ListRecords
{
    protected static string $resource = TimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
