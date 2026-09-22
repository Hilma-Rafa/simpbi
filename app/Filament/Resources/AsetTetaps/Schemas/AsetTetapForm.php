<?php

namespace App\Filament\Resources\AsetTetaps\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

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
                            ->label('Tim Kerja')
                            ->relationship('timPenempatan', 'nama_tim')
                            ->searchable()
                            ->preload()
                            ->placeholder('Belum ditempatkan')
                            // Aset yang sudah ditempatkan hanya berpindah lewat Mutasi Aset
                            // (BAST), sehingga pada form Ubah kolom ini terkunci dan tidak
                            // ikut disimpan (F-002). Form Buat, dan aset yang belum pernah
                            // ditempatkan, tetap dapat memilihnya: pengisian pertama itulah
                            // penempatan awal (EditAsetTetap::afterSave).
                            ->disabled(fn (?Model $record): bool => filled($record?->tim_penempatan_id))
                            ->dehydrated(fn (?Model $record): bool => blank($record?->tim_penempatan_id))
                            ->helperText(fn (?Model $record): ?string => filled($record?->tim_penempatan_id)
                                ? 'Penempatan diubah melalui Mutasi Aset (BAST).'
                                : null),
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
                        // Diisi impor dan sinkronisasi, bukan diketik (F-003): tampil
                        // tetapi tidak dapat diubah dan tidak ikut disimpan dari form.
                        TextInput::make('external_id')
                            ->label('ID Eksternal')
                            ->disabled()
                            ->dehydrated(false),
                        DateTimePicker::make('synced_at')
                            ->label('Waktu Sinkronisasi')
                            ->disabled()
                            ->dehydrated(false),
                    ]),
            ]);
    }
}
