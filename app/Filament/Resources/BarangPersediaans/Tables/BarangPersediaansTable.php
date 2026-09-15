<?php

namespace App\Filament\Resources\BarangPersediaans\Tables;

use App\Filament\Resources\BarangPersediaans\BarangPersediaanResource;
use App\Filament\Support\AksiHapusTerlindung;
use App\Filament\Support\KeadaanKosong;
use App\Models\BarangPersediaan;
use App\Services\EksporRiwayatService;
use App\Services\KartuKendaliService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
                    ->modalSubmitActionLabel('Cetak PDF')
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('5xl')
                    /**
                     * Isi modal ditempatkan pada schema, bukan pada
                     * modalContent(), karena data aksi baru terisi ketika
                     * aksinya dijalankan sehingga modalContent() tidak ikut
                     * berubah saat tahun dipilih. Komponen schema sebaliknya
                     * memang dirender ulang setiap keadaannya berubah.
                     */
                    ->schema(fn ($record) => [
                        Select::make('tahun')
                            ->label('Tahun')
                            ->options(app(KartuKendaliService::class)->tahunTersedia($record))
                            ->default(now()->year)
                            ->selectablePlaceholder(false)
                            ->native(false)
                            ->live()
                            ->helperText('Kartu kendali diterbitkan per tahun, sesuai kebiasaan pengarsipan Sub-Bagian Umum.'),

                        Placeholder::make('bukuBesar')
                            ->hiddenLabel()
                            ->content(fn (Get $get) => view(
                                'filament.partials.riwayat-mutasi',
                                app(KartuKendaliService::class)->data($record, (int) ($get('tahun') ?: now()->year)),
                            )),
                    ])
                    ->action(function ($record, array $data) {
                        $tahun = (int) ($data['tahun'] ?? now()->year);

                        $judul = 'Kartu Kendali ' . $record->nama_barang . ' ' . $tahun;

                        // Tampilan kartu kendali melayani sehimpunan kartu,
                        // sebab ekspor dari halaman Kartu Kendali menerbitkan
                        // seluruh barang sekaligus. Di sini himpunannya berisi
                        // satu kartu saja.
                        return app(EksporRiwayatService::class)->pdfTampilan($judul, 'pdf.kartu-kendali', [
                            'judul' => $judul,
                            'kartu' => [app(KartuKendaliService::class)->data($record, $tahun)],
                        ]);
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    AksiHapusTerlindung::massal(BarangPersediaanResource::ALASAN_TAK_DAPAT_DIHAPUS),
                ]),
            ]);
    }

}
