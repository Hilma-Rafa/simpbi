<?php

namespace App\Filament\Resources\Kategoris\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class KategoriForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama_kategori')
                    ->label('Nama Kategori')
                    ->required()
                    ->maxLength(100)
                    ->columnSpanFull(),
                TextInput::make('kode_kategori')
                    ->label('Kode Kategori')
                    ->required()
                    ->maxLength(20),
                TextInput::make('kode_akun')
                    ->label('Kode Akun')
                    ->helperText('Kode akun neraca sesuai bagan akun.')
                    ->required()
                    ->maxLength(10),
                Select::make('tipe')
                    ->label('Tipe')
                    ->options(['persediaan' => 'Persediaan', 'aset_tetap' => 'Aset Tetap'])
                    ->native(false)
                    ->required()
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
