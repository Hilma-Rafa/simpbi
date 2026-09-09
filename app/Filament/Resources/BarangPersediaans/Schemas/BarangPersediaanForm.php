<?php

namespace App\Filament\Resources\BarangPersediaans\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BarangPersediaanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Barang')
                    ->columns(2)
                    ->schema([
                        Select::make('kategori_id')
                            ->label('Kategori')
                            ->relationship('kategori', 'nama_kategori')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('kode_barang')
                            ->label('Kode Barang')
                            ->required()
                            ->maxLength(30),
                        TextInput::make('nama_barang')
                            ->label('Nama Barang')
                            ->required()
                            ->maxLength(150),
                        TextInput::make('satuan')
                            ->label('Satuan')
                            ->placeholder('mis. Rim, Box, Buah')
                            ->required()
                            ->maxLength(20),
                    ]),

                Section::make('Stok')
                    ->columns(3)
                    ->schema([
                        TextInput::make('stok_fisik')
                            ->label('Stok Fisik')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        TextInput::make('stok_hold')
                            ->label('Stok Terkunci')
                            ->helperText('Dikelola otomatis oleh sistem melalui mekanisme HOLD.')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        TextInput::make('stok_minimum')
                            ->label('Stok Minimum')
                            ->helperText('Isi 0 bila barang tidak dipantau terhadap stok minimum.')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        Toggle::make('status_aktif')
                            ->label('Barang Aktif')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }
}
