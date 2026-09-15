<?php

namespace App\Filament\Pages;

use App\Models\BarangPersediaan;
use App\Models\Kategori;
use App\Services\KartuKendaliService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kartu kendali persediaan seluruh barang.
 *
 * Sub-Bagian Umum selama ini memelihara kartu kendali di lembar sebar terpisah,
 * mencatat setiap pembelian dan setiap pemakaian dengan tangan — 471 baris
 * sepanjang 2025, dan satu kata yang sama sempat tertulis dalam empat ejaan
 * berbeda. Seluruh keterangan itu sebenarnya sudah tercatat sistem: pembelian
 * masuk lewat halaman Stok Masuk beserta nomor notanya, pemakaian lahir dari
 * permintaan barang yang disahkan beserta nomor bonnya.
 *
 * Halaman ini menyatukan keduanya menjadi kartu kendali yang siap diterbitkan,
 * sehingga pekerjaan yang sama tidak perlu dilakukan dua kali.
 *
 * Penyaringnya sengaja hanya periode dan kategori, bukan pemilihan transaksi
 * satu per satu. Kartu kendali adalah buku besar sebuah barang sepanjang
 * periode, dan kolom Sisa hanya bermakna bila seluruh transaksi barang itu
 * ikut; membiarkan sebagian dipilih akan menerbitkan kartu bersaldo salah,
 * yang lebih berbahaya daripada tidak menerbitkannya sama sekali.
 */
class KartuKendali extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.kartu-kendali';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Persediaan';

    protected static ?string $navigationLabel = 'Kartu Kendali';

    protected static ?string $title = 'Kartu Kendali Persediaan';

    protected static ?int $navigationSort = 4;

    /** Tahun periode yang sedang ditampilkan. */
    public int $tahun;

    public function mount(): void
    {
        $this->tahun = (int) now()->year;
    }

    /**
     * Petugas Gudang mencatat pergerakan stoknya, Kasubbag Umum yang
     * mempertanggungjawabkan dan merekonsiliasinya. Peran lain tidak
     * berkepentingan atas kartu kendali.
     */
    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['petugas_gudang', 'kasubbag']);
    }

    /** @return array<int,string> */
    public function tahunTersedia(): array
    {
        return app(KartuKendaliService::class)->tahunTersedia();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => BarangPersediaan::query()->with('kategori'))
            ->defaultSort('nama_barang')
            ->emptyStateIcon('heroicon-o-rectangle-stack')
            ->emptyStateHeading('Belum ada barang persediaan')
            ->emptyStateDescription('Kartu kendali terbit dari katalog barang; tambahkan barangnya lebih dulu.')
            ->columns([
                TextColumn::make('kode_barang')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nama_barang')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable()
                    ->description(fn (BarangPersediaan $record): string => $record->kategori?->nama_kategori ?? '—'),

                TextColumn::make('satuan')
                    ->label('Satuan')
                    ->alignCenter(),

                // Keempat kolom berikut diturunkan dari buku besar, bukan dari
                // kolom tabel, sehingga angkanya selalu sama dengan yang
                // tercetak pada kartunya.
                TextColumn::make('awal')
                    ->label('Stok Awal')
                    ->alignEnd()
                    ->state(fn (BarangPersediaan $record): int => $this->kartu($record)['awal']),

                TextColumn::make('masuk')
                    ->label('Masuk')
                    ->alignEnd()
                    // Warna hanya untuk angka yang benar-benar bergerak; tanda
                    // hubung justru menyatakan tidak ada apa-apa, sehingga
                    // mewarnainya membuat baris yang diam ikut menuntut perhatian.
                    ->color(fn (string $state): string => $state === '—' ? 'gray' : 'success')
                    ->state(fn (BarangPersediaan $record): string => $this->ringkas(
                        (int) $this->kartu($record)['mutasi']->where('jumlah', '>', 0)->sum('jumlah')
                    )),

                TextColumn::make('keluar')
                    ->label('Keluar')
                    ->alignEnd()
                    // Warna hanya untuk angka yang benar-benar bergerak; tanda
                    // hubung justru menyatakan tidak ada apa-apa, sehingga
                    // mewarnainya membuat baris yang diam ikut menuntut perhatian.
                    ->color(fn (string $state): string => $state === '—' ? 'gray' : 'danger')
                    ->state(fn (BarangPersediaan $record): string => $this->ringkas(
                        (int) abs($this->kartu($record)['mutasi']->where('jumlah', '<', 0)->sum('jumlah'))
                    )),

                TextColumn::make('akhir')
                    ->label('Sisa')
                    ->alignEnd()
                    ->weight('semibold')
                    ->state(fn (BarangPersediaan $record): int => $this->kartu($record)['akhir']),
            ])
            ->filters([
                SelectFilter::make('kategori')
                    ->label('Kategori')
                    ->relationship('kategori', 'nama_kategori')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('bergerak')
                    ->label('Pergerakan')
                    ->placeholder('Semua barang')
                    ->trueLabel('Ada pergerakan tahun ini')
                    ->falseLabel('Tidak bergerak tahun ini')
                    ->queries(
                        true: fn (Builder $q) => $q->whereHas(
                            'mutasi',
                            fn ($m) => $m->whereYear('tanggal', $this->tahun)
                        ),
                        false: fn (Builder $q) => $q->whereDoesntHave(
                            'mutasi',
                            fn ($m) => $m->whereYear('tanggal', $this->tahun)
                        ),
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->toolbarActions([
                Action::make('ekspor')
                    ->label('Ekspor Kartu Kendali')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->modalHeading('Ekspor Kartu Kendali')
                    ->modalDescription(
                        'Berkas berisi satu kartu per barang, mengikuti tata letak kartu kendali '
                        . 'Sub-Bagian Umum.'
                    )
                    ->modalSubmitActionLabel('Unduh')
                    ->modalCancelActionLabel('Batal')
                    ->schema([
                        /*
                         * PDF didahulukan dan menjadi pilihan bawaan: kartu
                         * kendali dipakai Sub-Bagian Umum untuk dicetak dan
                         * diarsipkan, bukan diolah. Berkas sebar tetap ada bagi
                         * yang memang perlu mengolah angkanya.
                         */
                        Select::make('format')
                            ->label('Format')
                            ->options([
                                'pdf'  => 'PDF — siap dicetak dan diarsipkan',
                                'xlsx' => 'Excel — untuk diolah lebih lanjut',
                            ])
                            ->default('pdf')
                            ->selectablePlaceholder(false)
                            ->native(false)
                            ->required(),

                        Select::make('tahun')
                            ->label('Periode')
                            ->options(fn (): array => $this->tahunTersedia())
                            ->default(fn (): int => $this->tahun)
                            ->selectablePlaceholder(false)
                            ->native(false)
                            ->required(),

                        Select::make('kategori_id')
                            ->label('Kategori')
                            ->options(fn (): array => Kategori::where('tipe', 'persediaan')
                                ->orderBy('nama_kategori')
                                ->pluck('nama_kategori', 'id')
                                ->all())
                            ->placeholder('Seluruh kategori')
                            ->native(false)
                            ->helperText('Kosongkan untuk menerbitkan kartu seluruh barang persediaan.'),
                    ])
                    ->action(function (array $data) {
                        $tahun = (int) $data['tahun'];

                        $barang = BarangPersediaan::query()
                            ->with('kategori')
                            ->when(
                                filled($data['kategori_id'] ?? null),
                                fn (Builder $q) => $q->where('kategori_id', $data['kategori_id'])
                            )
                            ->orderBy('kategori_id')
                            ->orderBy('kode_barang')
                            ->get();

                        $layanan = app(KartuKendaliService::class);

                        // Keduanya berangkat dari himpunan barang dan tahun yang
                        // sama persis, sehingga isi kedua berkas tidak mungkin
                        // berbeda — yang berbeda hanya wadahnya.
                        return ($data['format'] ?? 'pdf') === 'xlsx'
                            ? $layanan->spreadsheet($barang, $tahun)
                            : $layanan->pdf($barang, $tahun);
                    }),
            ]);
    }

    /**
     * Data kartu satu barang untuk periode yang sedang dipilih.
     *
     * Hasilnya disimpan sementara per barang karena satu baris tabel membaca
     * empat kolom yang semuanya bersumber dari kartu yang sama; tanpa ini
     * setiap baris menjalankan empat kali kueri yang identik.
     *
     * @return array<string,mixed>
     */
    protected function kartu(BarangPersediaan $barang): array
    {
        static $simpanan = [];

        $kunci = $barang->id . ':' . $this->tahun;

        return $simpanan[$kunci] ??= app(KartuKendaliService::class)->data($barang, $this->tahun);
    }

    /** Angka nol ditampilkan sebagai garis agar baris yang diam tidak ramai. */
    protected function ringkas(int $nilai): string
    {
        return $nilai === 0 ? '—' : (string) $nilai;
    }
}
