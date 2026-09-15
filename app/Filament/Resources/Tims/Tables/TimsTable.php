<?php

namespace App\Filament\Resources\Tims\Tables;

use App\Filament\Support\AksiImpor;
use App\Services\Impor\ImporTimKerja;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TimsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nama_tim')
            ->columns([
                TextColumn::make('nama_tim')
                    ->label('Nama Tim')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('ketuaTim.name')
                    ->label('Ketua Tim')
                    ->placeholder('Belum ditetapkan')
                    ->searchable(),

                /**
                 * Jumlah anggota ditulis sebagai angka biasa, bukan lencana:
                 * angka yang dibungkus pil sulit dibandingkan antar baris
                 * karena lebar pilnya ikut berubah mengikuti isinya.
                 */
                TextColumn::make('anggota_count')
                    ->label('Anggota')
                    ->counts('anggota')
                    ->alignEnd(),

                /** Lihat catatan yang sama pada tabel Barang Persediaan. */
                IconColumn::make('status_aktif')
                    ->label('Aktif')
                    ->boolean()
                    ->trueColor('gray')
                    ->falseColor('danger'),

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
                TernaryFilter::make('status_aktif')
                    ->label('Status')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->headerActions([
                AksiImpor::buat(
                    judul: ImporTimKerja::JUDUL,
                    kolom: ImporTimKerja::kolom(),
                    namaTemplate: 'Template-Impor-Tim-Kerja.xlsx',
                    impor: fn (string $lintasan) => app(ImporTimKerja::class)->jalankan($lintasan),
                ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
