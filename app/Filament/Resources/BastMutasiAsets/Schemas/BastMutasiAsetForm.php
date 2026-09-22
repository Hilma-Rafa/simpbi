<?php

namespace App\Filament\Resources\BastMutasiAsets\Schemas;

use App\Models\AsetTetap;
use App\Services\MutasiAsetService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Notifications\Notification;
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
                            // Tim kerja asal otomatis mengikuti penempatan aset saat ini.
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $aset = AsetTetap::find($state);
                                $set('tim_asal_id', $aset?->tim_penempatan_id);

                                if ($aset && blank($aset->tim_penempatan_id)) {
                                    Notification::make()
                                        ->title('Aset belum memiliki penempatan')
                                        ->body(app(MutasiAsetService::class)->pesanAsetTidakDapatDimutasi($aset))
                                        ->warning()
                                        ->send();
                                }
                            })
                            // Aset tanpa penempatan, atau yang masih punya BAST menunggu
                            // pengesahan, tidak dapat dimutasi. Pemeriksaan yang mengikat
                            // ada di server (MutasiAsetService::periksaPembuatan).
                            ->rule(static fn (): \Closure => static function (string $attribute, $value, \Closure $fail): void {
                                $aset = filled($value) ? AsetTetap::find($value) : null;

                                if ($aset && ($pesan = app(MutasiAsetService::class)->pesanAsetTidakDapatDimutasi($aset))) {
                                    $fail($pesan);
                                }
                            })
                            ->columnSpanFull(),
                        Select::make('tim_asal_id')
                            ->label('Tim Kerja Asal')
                            ->relationship('timAsal', 'nama_tim')
                            ->required()
                            // Selalu mengikuti penempatan aset saat ini; tidak dapat diubah.
                            ->disabled()
                            ->dehydrated()
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
