<?php

namespace App\Filament\Resources\Kategoris\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tipe')
                    ->label('Tipe')
                    ->options(self::TIPE_LABEL),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
