<?php

namespace App\Filament\Resources\Tims\Tables;

use App\Filament\Support\AksiImpor;
use App\Services\Impor\ImporTimKerja;
use Filament\Actions\BulkActionGroup;
use App\Filament\Support\AksiHapusTerlindung;
use App\Filament\Support\KeadaanKosong;
use App\Filament\Resources\Tims\TimResource;
use App\Filament\Support\AksiUbah;
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
            /**
             * Kalimat keadaan kosong dibedakan: daftar yang memang belum berisi
             * memerlukan ajakan mengisi, sedangkan pencarian yang tidak
             * menemukan apa pun memerlukan jalan keluar dari penyaringnya.
             */
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateHeading(fn ($livewire): string => KeadaanKosong::sedangDisaring($livewire)
                ? 'Tidak ada tim kerja yang cocok'
                : 'Belum ada tim kerja')
            ->emptyStateDescription(fn ($livewire): string => KeadaanKosong::sedangDisaring($livewire)
                ? 'Coba longgarkan penyaringnya, atau periksa kembali ejaan nama tim yang dicari.'
                : 'Permintaan barang diajukan atas nama tim kerja. Tambahkan tim kerja beserta ketuanya agar permintaan dapat diajukan.')
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
                    ->dateTime('d-m-Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d-m-Y')
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
                AksiUbah::buat(),
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
                    AksiHapusTerlindung::massal(TimResource::ALASAN_TAK_DAPAT_DIHAPUS),
                ]),
            ]);
    }
}
