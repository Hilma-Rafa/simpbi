<?php

namespace App\Filament\Resources\BastMutasiAsets\Schemas;

use App\Models\AsetTetap;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Form pembuatan BAST mutasi aset (UC-16). Data hasil koordinasi di luar
 * sistem (NUP, tim asal, tim tujuan, alasan) dicatat oleh Petugas Gudang.
 */
class BastMutasiAsetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Aset yang Dimutasi')
                    ->columns(2)
                    ->schema([
                        Select::make('aset_id')
                            ->label('Aset (NUP — Nama)')
                            ->relationship('aset', 'nama_aset', fn ($query) => $query->where('status_aktif', true))
                            ->getOptionLabelFromRecordUsing(fn (AsetTetap $r): string => "{$r->nup} — {$r->nama_aset}")
                            ->searchable(['nup', 'nama_aset'])
                            ->preload()
                            ->required()
                            ->live()
                            // Unit asal otomatis mengikuti penempatan aset saat ini.
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $aset = AsetTetap::find($state);
                                $set('tim_asal_id', $aset?->tim_penempatan_id);
                            })
                            ->columnSpanFull(),
                        Select::make('tim_asal_id')
                            ->label('Tim Kerja Asal')
                            ->relationship('timAsal', 'nama_tim')
                            ->required()
                            ->helperText('Terisi otomatis dari penempatan aset saat ini.'),
                        Select::make('tim_tujuan_id')
                            ->label('Tim Kerja Tujuan')
                            ->relationship('timTujuan', 'nama_tim')
                            ->required()
                            ->different('tim_asal_id'),
                    ]),

                Section::make('Berita Acara')
                    ->columns(2)
                    ->schema([
                        Textarea::make('alasan_mutasi')
                            ->label('Alasan Mutasi')
                            ->required()
                            ->rows(2)
                            ->columnSpanFull(),
                        TextInput::make('pihak_penyerah')
                            ->label('Pihak Penyerah')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('pihak_penerima')
                            ->label('Pihak Penerima')
                            ->required()
                            ->maxLength(100),
                    ]),
            ]);
    }
}
