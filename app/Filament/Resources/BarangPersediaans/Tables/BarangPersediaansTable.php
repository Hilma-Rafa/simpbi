<?php

namespace App\Filament\Resources\BarangPersediaans\Tables;

use App\Filament\Support\KeadaanKosong;
use App\Models\BarangPersediaan;
use App\Services\EksporRiwayatService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
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
                            ->options(static::tahunTersedia($record))
                            ->default(now()->year)
                            ->selectablePlaceholder(false)
                            ->native(false)
                            ->live()
                            ->helperText('Kartu kendali diterbitkan per tahun, sesuai kebiasaan pengarsipan Sub-Bagian Umum.'),

                        Placeholder::make('bukuBesar')
                            ->hiddenLabel()
                            ->content(fn (Get $get) => view(
                                'filament.partials.riwayat-mutasi',
                                static::dataKartuKendali($record, (int) ($get('tahun') ?: now()->year)),
                            )),
                    ])
                    ->action(function ($record, array $data) {
                        $tahun = (int) ($data['tahun'] ?? now()->year);

                        return app(EksporRiwayatService::class)->pdfTampilan(
                            'Kartu Kendali ' . $record->nama_barang . ' ' . $tahun,
                            'pdf.kartu-kendali',
                            static::dataKartuKendali($record, $tahun),
                        );
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    // =====================================================================
    // KARTU KENDALI
    // =====================================================================

    /**
     * Tahun yang dapat dipilih untuk suatu barang.
     *
     * Berisi tahun-tahun yang benar-benar memiliki mutasi, ditambah tahun
     * berjalan supaya kartu tahun ini tetap dapat dicetak meski belum ada
     * transaksi. Urutan menurun agar tahun terbaru berada di paling atas.
     *
     * @return array<int,string>
     */
    protected static function tahunTersedia(BarangPersediaan $barang): array
    {
        $tahun = $barang->mutasi()
            ->selectRaw('DISTINCT ' . static::petikTahun() . ' AS tahun')
            ->pluck('tahun')
            ->map(fn ($t) => (int) $t)
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values();

        return $tahun->mapWithKeys(fn (int $t) => [$t => (string) $t])->all();
    }

    /**
     * Pemetikan tahun dari kolom tanggal.
     *
     * MySQL dan SQLite memakai fungsi yang berbeda, sedangkan lingkungan
     * sebenarnya MySQL (Instruksi §52) dan lingkungan pengembangan lokal
     * memakai SQLite, sehingga keduanya perlu dilayani.
     */
    protected static function petikTahun(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%Y', tanggal) AS INTEGER)"
            : 'YEAR(tanggal)';
    }

    /**
     * Data satu kartu kendali, dipakai bersama oleh tampilan di layar dan
     * hasil cetaknya agar keduanya tidak mungkin menampilkan angka berbeda.
     *
     * Stok awal diambil dari saldo transaksi terakhir sebelum periode, bukan
     * dihitung mundur dari stok fisik, sebab kartu kendali harus mencerminkan
     * buku besar apa adanya. Bila barang belum memiliki mutasi sebelum periode
     * itu, stok awalnya nol dan selisih terhadap stok fisik justru terlihat
     * sebagai temuan. Stok akhir memakai saldo transaksi terakhir di dalam
     * periode, dan kembali ke stok awal bila periodenya kosong.
     *
     * @return array<string,mixed>
     */
    protected static function dataKartuKendali(BarangPersediaan $barang, int $tahun): array
    {
        // Batas periode ditulis sebagai tanggal murni, tanpa jam. Kolom tanggal
        // bertipe DATE, sehingga membandingkannya dengan nilai bertanda waktu
        // membuat transaksi 1 Januari terbuang pada SQLite yang membandingkan
        // keduanya sebagai teks.
        $mulai   = Carbon::create($tahun, 1, 1)->toDateString();
        $selesai = Carbon::create($tahun, 12, 31)->toDateString();

        $awal = (int) ($barang->mutasi()
            ->whereDate('tanggal', '<', $mulai)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->value('saldo_sesudah') ?? 0);

        $mutasi = $barang->mutasi()
            ->with('petugas')
            ->whereBetween('tanggal', [$mulai, $selesai])
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        return [
            'barang'  => $barang->loadMissing('kategori'),
            'mutasi'  => $mutasi,
            'tahun'   => $tahun,
            'periode' => 'Januari s.d. Desember ' . $tahun,
            'awal'    => $awal,
            'akhir'   => $mutasi->isNotEmpty() ? (int) $mutasi->last()->saldo_sesudah : $awal,
        ];
    }
}