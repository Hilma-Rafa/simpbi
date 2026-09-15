<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Support\AksiImpor;
use App\Services\Impor\ImporPengguna;
use Filament\Actions\BulkActionGroup;
use App\Filament\Support\AksiHapusTerlindung;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    /** Warna badge peran (sejalan dengan makna warna Instruksi §25). */
    protected const ROLE_COLORS = [
        'admin'          => 'gray',
        'kasubbag'       => 'primary',
        'petugas_gudang' => 'info',
        'ketua_tim'      => 'warning',
        'tim'            => 'success',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->description(fn ($record) => $record->username)
                    ->searchable(['name', 'username'])
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('role')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => UserForm::ROLE_OPTIONS[$state] ?? $state)
                    ->color(fn (string $state): string => self::ROLE_COLORS[$state] ?? 'gray')
                    ->sortable(),
                TextColumn::make('tim.nama_tim')
                    ->label('Tim Kerja')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                /** Lihat catatan yang sama pada tabel Barang Persediaan. */
                IconColumn::make('status_aktif')
                    ->label('Aktif')
                    ->boolean()
                    ->trueColor('gray')
                    ->falseColor('danger'),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Peran')
                    ->options(UserForm::ROLE_OPTIONS),
                TernaryFilter::make('status_aktif')
                    ->label('Status Akun')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->headerActions([
                AksiImpor::buat(
                    judul: ImporPengguna::JUDUL,
                    kolom: ImporPengguna::kolom(),
                    namaTemplate: 'Template-Impor-Pengguna.xlsx',
                    impor: fn (string $lintasan) => app(ImporPengguna::class)->jalankan($lintasan),
                ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    AksiHapusTerlindung::massal(UserResource::ALASAN_TAK_DAPAT_DIHAPUS),
                ]),
            ]);
    }
}
