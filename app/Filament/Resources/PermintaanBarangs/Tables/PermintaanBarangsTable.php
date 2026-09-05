<?php

namespace App\Filament\Resources\PermintaanBarangs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PermintaanBarangsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_permintaan')
                    ->searchable(),
                TextColumn::make('tim_pemohon_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('pengaju_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('nama_pemohon')
                    ->searchable(),
                TextColumn::make('nip_pemohon')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('hold_expired_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('hold_released_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('file_bukti_path')
                    ->searchable(),
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
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
