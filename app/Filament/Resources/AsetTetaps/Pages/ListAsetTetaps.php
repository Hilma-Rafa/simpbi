<?php

namespace App\Filament\Resources\AsetTetaps\Pages;

use App\Filament\Resources\AsetTetaps\AsetTetapResource;
use App\Filament\Resources\AsetTetaps\Widgets\StatusSinkronisasiAsetTetap;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAsetTetaps extends ListRecords
{
    protected static string $resource = AsetTetapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StatusSinkronisasiAsetTetap::class,
        ];
    }
}
