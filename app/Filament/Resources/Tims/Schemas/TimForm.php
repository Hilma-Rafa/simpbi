<?php

namespace App\Filament\Resources\Tims\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;

class TimForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama_tim')
                    ->required(),
                Select::make('ketua_tim_id')
                    ->label('Ketua Tim')
                    ->relationship('ketuaTim', 'name'),
                TextInput::make('external_id'),
                DateTimePicker::make('synced_at'),
                Toggle::make('status_aktif')
                    ->required(),
            ]);
    }
}
