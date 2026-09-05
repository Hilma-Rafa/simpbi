<?php

namespace App\Filament\Resources\PermintaanBarangs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PermintaanBarangInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('kode_permintaan'),
                TextEntry::make('tim_pemohon_id')
                    ->numeric(),
                TextEntry::make('pengaju_id')
                    ->numeric(),
                TextEntry::make('nama_pemohon'),
                TextEntry::make('nip_pemohon')
                    ->placeholder('-'),
                TextEntry::make('keterangan_keperluan')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('hold_expired_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('hold_released_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('file_bukti_path')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
