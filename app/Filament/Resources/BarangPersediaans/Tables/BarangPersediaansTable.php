<?php

namespace App\Filament\Resources\BarangPersediaans\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BarangPersediaansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nama_barang')
            ->columns([
                TextColumn::make('nama_barang')
                    ->label('Nama Barang')
                    ->description(fn ($record) => $record->kode_barang)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('kategori.nama_kategori')
                    ->label('Kategori')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('satuan')
                    ->label('Satuan'),

                TextColumn::make('stok_fisik')
                    ->label('Stok Fisik')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('stok_hold')
                    ->label('Terkunci')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),

                TextColumn::make('stok_tersedia')
                    ->label('Tersedia')
                    ->state(fn ($record) => $record->stok_fisik - $record->stok_hold)
                    ->badge()
                    ->alignEnd()
                    ->color(fn ($state, $record) => match (true) {
                        $state <= 0                                                  => 'danger',
                        $record->stok_minimum > 0 && $state <= $record->stok_minimum => 'warning',
                        default                                                      => 'success',
                    }),

                TextColumn::make('stok_minimum')
                    ->label('Minimum')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(),

                IconColumn::make('status_aktif')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('kategori_id')
                    ->label('Kategori')
                    ->relationship('kategori', 'nama_kategori')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('status_aktif')
                    ->label('Status')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
            ])
            ->recordActions([

                /**
                 * Kartu kendali persediaan per barang.
                 *
                 * Menyajikan buku besar pergerakan barang beserta saldo berjalan,
                 * mengikuti kolom Kartu Kendali Barang Persediaan yang digunakan
                 * Sub-Bagian Umum, sehingga dapat dipakai sebagai bahan rekonsiliasi.
                 */
                Action::make('kartuKendali')
                    ->label('Kartu Kendali')
                    ->icon('heroicon-m-clipboard-document-list')
                    ->color('gray')
                    ->outlined()
                    ->modalHeading(fn ($record) => 'Kartu Kendali — ' . $record->nama_barang)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('5xl')
                    ->modalContent(fn ($record) => view('filament.partials.riwayat-mutasi', [
                        'barang' => $record->load('kategori'),
                        'mutasi' => $record->mutasi()
                            ->with('petugas')
                            ->orderBy('tanggal')
                            ->orderBy('id')
                            ->get(),
                    ])),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}