<?php

namespace App\Filament\Resources\AsetTetaps\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AsetTetapForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nup')
                    ->required(),
                TextInput::make('nama_aset')
                    ->required(),
                Select::make('kategori_id')
                    ->label('Kategori')
                    ->relationship('kategori', 'nama_kategori')
                    ->required(),
                Select::make('tim_penempatan_id')
                    ->label('Tim Penempatan')
                    ->relationship('timPenempatan', 'nama_tim'),
                Select::make('kondisi')
                    ->options(['baik' => 'Baik', 'rusak_ringan' => 'Rusak ringan', 'rusak_berat' => 'Rusak berat'])
                    ->default('baik')
                    ->required(),
                Select::make('sumber_data')
                    ->options(['manual' => 'Manual', 'impor' => 'Impor', 'api' => 'Api'])
                    ->default('manual')
                    ->required(),
                TextInput::make('external_id'),
                DateTimePicker::make('synced_at'),
                Toggle::make('status_aktif')
                    ->required(),
            ]);
    }
}
