<?php

namespace App\Filament\Resources\BarangPersediaans\Tables;

use App\Filament\Resources\BarangPersediaans\BarangPersediaanResource;
use App\Filament\Support\AksiHapusTerlindung;
use App\Filament\Support\AksiUbah;
use App\Filament\Support\KeadaanKosong;
use App\Models\BarangPersediaan;
use Filament\Actions\BulkActionGroup;
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
            /**
             * Kalimat keadaan kosong dibedakan: daftar yang memang belum
             * berisi memerlukan ajakan mengisi, sedangkan pencarian yang tidak
             * menemukan apa pun memerlukan jalan keluar dari penyaringnya.
             */
            ->emptyStateIcon('heroicon-o-archive-box')
            ->emptyStateHeading(fn ($livewire): string => KeadaanKosong::sedangDisaring($livewire)
                ? 'Tidak ada barang yang cocok'
                : 'Katalog persediaan masih kosong')
            ->emptyStateDescription(fn ($livewire): string => KeadaanKosong::sedangDisaring($livewire)
                ? 'Coba longgarkan penyaringnya, atau periksa kembali ejaan kata yang dicari.'
                : 'Tambahkan barang beserta satuan dan stok minimumnya, agar tim kerja dapat mengajukan permintaan.')
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

                /**
                 * Stok tersedia hanya dijadikan lencana ketika angkanya memang
                 * menuntut tindakan, yaitu habis atau sudah menyentuh stok
                 * minimum. Sebelumnya setiap baris berlencana, termasuk baris
                 * yang baik-baik saja, sehingga sekolom penuh warna dan mata
                 * tidak lagi dapat menemukan baris yang bermasalah.
                 */
                TextColumn::make('stok_tersedia')
                    ->label('Tersedia')
                    ->state(fn ($record) => $record->stok_fisik - $record->stok_hold)
                    ->alignEnd()
                    ->badge(fn ($state, $record) => $state <= 0
                        || ($record->stok_minimum > 0 && $state <= $record->stok_minimum))
                    ->color(fn ($state, $record) => match (true) {
                        $state <= 0                                                  => 'danger',
                        $record->stok_minimum > 0 && $state <= $record->stok_minimum => 'warning',
                        default                                                      => null,
                    }),

                TextColumn::make('stok_minimum')
                    ->label('Minimum')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(),

                /**
                 * Barang aktif adalah keadaan biasa, jadi tandanya dibuat
                 * netral; yang berwarna hanya barang nonaktif, sebab itulah
                 * pengecualian yang perlu ditemukan pembaca (Instruksi §25).
                 */
                IconColumn::make('status_aktif')
                    ->label('Aktif')
                    ->boolean()
                    ->trueColor('gray')
                    ->falseColor('danger'),
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

                /*
                 * Tombol "Kartu Kendali" per baris dihapus: melihat dan
                 * menerbitkan kartu kendali kini dipusatkan pada halaman
                 * Kartu Kendali, yang menyediakan pratinjau ledger rinci per
                 * barang sekaligus ekspornya. Menaruh keduanya di dua tempat
                 * hanya menggandakan satu fungsi yang sama.
                 */
                AksiUbah::buat(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    AksiHapusTerlindung::massal(BarangPersediaanResource::ALASAN_TAK_DAPAT_DIHAPUS),
                ]),
            ]);
    }

}
