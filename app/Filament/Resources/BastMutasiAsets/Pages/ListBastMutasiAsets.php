<?php

namespace App\Filament\Resources\BastMutasiAsets\Pages;

use App\Filament\Resources\BastMutasiAsets\BastMutasiAsetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBastMutasiAsets extends ListRecords
{
    protected static string $resource = BastMutasiAsetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat BAST'),

            // Pintasan ke halaman Riwayat dengan jenis Mutasi Aset terpilih
            \Filament\Actions\Action::make('riwayat')
                ->label('Riwayat')
                ->icon('heroicon-m-archive-box')
                ->iconPosition(\Filament\Support\Enums\IconPosition::Before)
                ->color('gray')
                ->outlined()
                ->tooltip('Lihat BAST mutasi aset yang sudah disahkan')
                ->url(\App\Filament\Pages\Riwayat::getUrl(['jenis' => 'mutasi_aset']))
                ->visible(fn () => \App\Filament\Pages\Riwayat::canAccess()),
        ];
    }
}
