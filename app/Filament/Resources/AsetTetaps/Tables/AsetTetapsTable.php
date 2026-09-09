<?php

namespace App\Filament\Resources\AsetTetaps\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AsetTetapsTable
{
    public const KONDISI_LABEL = [
        'baik'         => 'Baik',
        'rusak_ringan' => 'Rusak Ringan',
        'rusak_berat'  => 'Rusak Berat',
    ];

    protected const KONDISI_COLOR = [
        'baik'         => 'success',
        'rusak_ringan' => 'warning',
        'rusak_berat'  => 'danger',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nama_aset')
            ->columns([
                TextColumn::make('nama_aset')
                    ->label('Nama Aset')
                    ->description(fn ($record) => 'NUP ' . $record->nup)
                    ->searchable(['nama_aset', 'nup'])
                    ->sortable()
                    ->wrap(),

                TextColumn::make('kategori.nama_kategori')
                    ->label('Kategori')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('timPenempatan.nama_tim')
                    ->label('Unit Penempatan')
                    ->placeholder('Belum ditempatkan')
                    ->toggleable(),

                TextColumn::make('kondisi')
                    ->label('Kondisi')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::KONDISI_LABEL[$state] ?? $state)
                    ->color(fn (string $state): string => self::KONDISI_COLOR[$state] ?? 'gray'),

                IconColumn::make('status_aktif')
                    ->label('Aktif')
                    ->boolean(),

                TextColumn::make('sumber_data')
                    ->label('Sumber Data')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => $state === 'api' ? 'API' : ucfirst((string) $state))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('synced_at')
                    ->label('Tersinkron')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('kategori_id')
                    ->label('Kategori')
                    ->relationship('kategori', 'nama_kategori')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('kondisi')
                    ->label('Kondisi')
                    ->options(self::KONDISI_LABEL),

                SelectFilter::make('tim_penempatan_id')
                    ->label('Unit Penempatan')
                    ->relationship('timPenempatan', 'nama_tim'),

                TernaryFilter::make('status_aktif')
                    ->label('Status')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
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
