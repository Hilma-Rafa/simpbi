<?php

namespace App\Filament\Resources\Tims\Pages;

use App\Filament\Resources\Tims\TimResource;
use App\Filament\Resources\Tims\Widgets\StatusSinkronisasiTim;
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

    protected function getHeaderWidgets(): array
    {
        return [
            StatusSinkronisasiTim::class,
        ];
    }
}
