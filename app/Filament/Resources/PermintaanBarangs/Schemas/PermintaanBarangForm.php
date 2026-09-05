<?php

namespace App\Filament\Resources\PermintaanBarangs\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PermintaanBarangForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode_permintaan')
                    ->required(),
                TextInput::make('tim_pemohon_id')
                    ->required()
                    ->numeric(),
                TextInput::make('pengaju_id')
                    ->required()
                    ->numeric(),
                TextInput::make('nama_pemohon')
                    ->required(),
                TextInput::make('nip_pemohon'),
                Textarea::make('keterangan_keperluan')
                    ->columnSpanFull(),
                Select::make('status')
                    ->options([
            'menunggu_ketua' => 'Menunggu ketua',
            'menunggu_verifikasi' => 'Menunggu verifikasi',
            'menunggu_kasubbag' => 'Menunggu kasubbag',
            'siap_diproses' => 'Siap diproses',
            'siap_diambil' => 'Siap diambil',
            'selesai' => 'Selesai',
            'ditolak_ketua' => 'Ditolak ketua',
            'ditolak_kasubbag' => 'Ditolak kasubbag',
            'bermasalah' => 'Bermasalah',
            'kedaluwarsa' => 'Kedaluwarsa',
        ])
                    ->default('menunggu_ketua')
                    ->required(),
                DateTimePicker::make('hold_expired_at'),
                DateTimePicker::make('hold_released_at'),
                TextInput::make('file_bukti_path'),
            ]);
    }
}
