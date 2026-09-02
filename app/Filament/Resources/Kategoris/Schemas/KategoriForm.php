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
                TextInput::make('kode_akun')
                    ->required(),
                TextInput::make('kode_kategori')
                    ->required(),
                TextInput::make('nama_kategori')
                    ->required(),
                Select::make('tipe')
                    ->options(['persediaan' => 'Persediaan', 'aset_tetap' => 'Aset tetap'])
                    ->required(),
            ]);
    }
}
