<?php

namespace App\Filament\Resources\BarangPersediaans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;

class BarangPersediaanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('kategori_id')
                    ->label('Kategori')
                    ->relationship('kategori', 'nama_kategori')
                    ->required(),
                TextInput::make('kode_barang')
                    ->required(),
                TextInput::make('nama_barang')
                    ->required(),
                TextInput::make('satuan')
                    ->required(),
                TextInput::make('stok_fisik')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('stok_hold')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('stok_minimum')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('status_aktif')
                    ->required(),
            ]);
    }
}
