<?php

namespace App\Filament\Resources\Tims\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TimForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Tim')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nama_tim')
                            ->label('Nama Tim')
                            ->required()
                            ->maxLength(150)
                            ->columnSpanFull(),
                        Select::make('ketua_tim_id')
                            ->label('Ketua Tim')
                            ->relationship('ketuaTim', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Belum ditetapkan'),
                        Toggle::make('status_aktif')
                            ->label('Tim Aktif')
                            ->default(true)
                            ->inline(false),
                    ]),

                Section::make('Sinkronisasi Data')
                    ->description('Diisi otomatis saat sinkronisasi data tim kerja.')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextInput::make('external_id')
                            ->label('ID Eksternal')
                            ->maxLength(50),
                        DateTimePicker::make('synced_at')
                            ->label('Waktu Sinkronisasi'),
                    ]),
            ]);
    }
}
