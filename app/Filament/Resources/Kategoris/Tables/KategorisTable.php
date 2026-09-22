<?php

namespace App\Filament\Resources\Kategoris\Tables;

use App\Filament\Resources\Kategoris\KategoriResource;
use App\Filament\Support\AksiHapusTerlindung;
use App\Filament\Support\KeadaanKosong;
use Filament\Actions\BulkActionGroup;
use App\Filament\Support\AksiUbah;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class KategorisTable
{
    public const TIPE_LABEL = [
        'persediaan' => 'Persediaan',
        'aset_tetap' => 'Aset Tetap',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nama_kategori')
            /**
             * Kalimat keadaan kosong dibedakan: daftar yang memang belum berisi
             * memerlukan ajakan mengisi, sedangkan pencarian yang tidak
             * menemukan apa pun memerlukan jalan keluar dari penyaringnya.
             */
            ->emptyStateIcon('heroicon-o-tag')
            ->emptyStateHeading(fn ($livewire): string => KeadaanKosong::sedangDisaring($livewire)
                ? 'Tidak ada kategori yang cocok'
                : 'Belum ada kategori')
            ->emptyStateDescription(fn ($livewire): string => KeadaanKosong::sedangDisaring($livewire)
                ? 'Coba longgarkan penyaringnya, atau periksa kembali ejaan kata yang dicari.'
                : 'Kategori menaungi barang persediaan dan aset tetap. Tambahkan kategori lebih dulu sebelum mengisi katalog.')
            ->columns([
                TextColumn::make('nama_kategori')
                    ->label('Nama Kategori')
                    ->description(fn ($record) => 'Kode ' . $record->kode_kategori)
                    ->searchable(['nama_kategori', 'kode_kategori'])
                    ->sortable(),

                TextColumn::make('kode_akun')
                    ->label('Kode Akun')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->toggleable(),

                /**
                 * Tipe kategori bukan keadaan yang menuntut perhatian,
                 * melainkan penggolongan. Jingga karena itu dilepas: pada
                 * Instruksi 25 jingga berarti perlu perhatian, dan memakainya
                 * untuk penggolongan membuat maknanya luntur di seluruh sistem.
                 */
                TextColumn::make('tipe')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::TIPE_LABEL[$state] ?? $state)
                    ->color(fn (string $state): string => $state === 'aset_tetap' ? 'gray' : 'info'),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d-m-Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tipe')
                    ->label('Tipe')
                    ->options(self::TIPE_LABEL),
            ])
            ->recordActions([
                AksiUbah::buat(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    AksiHapusTerlindung::massal(KategoriResource::ALASAN_TAK_DAPAT_DIHAPUS),
                ]),
            ]);
    }
}
