<?php

namespace App\Filament\Resources\AsetTetaps\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AsetTetapForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Aset')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nup')
                            ->label('NUP')
                            ->helperText('Nomor Urut Pendaftaran aset.')
                            ->required()
                            ->maxLength(30),
                        TextInput::make('nama_aset')
                            ->label('Nama Aset')
                            ->required()
                            ->maxLength(150),
                        Select::make('kategori_id')
                            ->label('Kategori')
                            ->relationship('kategori', 'nama_kategori')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Toggle::make('status_aktif')
                            ->label('Aset Aktif')
                            ->default(true)
                            ->inline(false),
                    ]),

                Section::make('Penempatan & Kondisi')
                    ->columns(2)
                    ->schema([
                        Select::make('tim_penempatan_id')
                            ->label('Unit Penempatan')
                            ->relationship('timPenempatan', 'nama_tim')
                            ->searchable()
                            ->preload()
                            ->placeholder('Belum ditempatkan'),
                        Select::make('kondisi')
                            ->label('Kondisi')
                            ->options([
                                'baik'         => 'Baik',
                                'rusak_ringan' => 'Rusak Ringan',
                                'rusak_berat'  => 'Rusak Berat',
                            ])
                            ->default('baik')
                            ->native(false)
                            ->required(),
                    ]),

                Section::make('Sinkronisasi Data')
                    ->description('Diisi otomatis saat impor atau sinkronisasi data aset.')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        Select::make('sumber_data')
                            ->label('Sumber Data')
                            ->options(['manual' => 'Manual', 'impor' => 'Impor', 'api' => 'API'])
                            ->default('manual')
                            ->native(false)
                            ->required(),
                        TextInput::make('external_id')
                            ->label('ID Eksternal')
                            ->maxLength(50),
                        DateTimePicker::make('synced_at')
                            ->label('Waktu Sinkronisasi'),
                    ]),
            ]);
    }
}
