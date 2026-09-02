<?php

namespace App\Filament\Resources\AsetTetaps\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AsetTetapsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nup')
                    ->searchable(),
                TextColumn::make('nama_aset')
                    ->searchable(),
                TextColumn::make('kategori_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('tim_penempatan_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('kondisi')
                    ->badge(),
                TextColumn::make('sumber_data')
                    ->badge(),
                TextColumn::make('external_id')
                    ->searchable(),
                TextColumn::make('synced_at')
                    ->dateTime()
                    ->sortable(),
                IconColumn::make('status_aktif')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
