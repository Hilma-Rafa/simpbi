<?php

namespace App\Filament\Pages;

use App\Filament\Resources\BastMutasiAsets\BastMutasiAsetResource;
use App\Filament\Resources\PermintaanBarangs\PermintaanBarangResource;
use App\Models\MutasiStok;
use App\Models\PermintaanBarang;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Livewire\Attributes\Url;

/**
 * Pusat riwayat SIMPBI.
 *
 * Menyatukan seluruh data yang sudah menjadi histori pada satu halaman, agar
 * pengguna tidak perlu membuka banyak menu terpisah. Jenis riwayat dipilih
 * melalui tombol di bagian atas, dan pilihan tersebut tersimpan pada alamat
 * halaman sehingga tautan pintas dari menu lain dapat langsung membuka jenis
 * yang dituju.
 *
 * Setiap jenis memakai kembali cakupan peran yang sudah berlaku pada modul
 * asalnya, bukan aturan baru:
 *   Permintaan Barang : PermintaanBarangResource::getEloquentQuery()
 *   Mutasi Aset       : BastMutasiAsetResource::getEloquentQuery()
 *   Mutasi Stok       : buku besar mutasi_stok, bagi pengelola persediaan
 *
 * Ekspor PDF dan berkas sebar mengikuti penyaring yang sedang aktif, sebab
 * barisnya diambil dari kueri tabel yang sudah tersaring.
 */
class Riwayat extends Page implements HasTable
{
    use InteractsWithTable;
    use Concerns\MengeksporRiwayat;

    protected string $view = 'filament.pages.riwayat';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|\UnitEnum|null $navigationGroup = 'Monitoring';

    protected static ?string $navigationLabel = 'Riwayat';

    protected static ?string $title = 'Riwayat';

    protected static ?int $navigationSort = 1;

    /** Jenis riwayat yang sedang dilihat; tersimpan pada alamat halaman. */
    #[Url]
    public ?string $jenis = null;

    /**
     * Penyaring tabel diikat ke alamat halaman seperti halaman daftar Filament,
     * sehingga tautan pintas dapat membuka riwayat yang sudah tersaring.
     *
     * @var array<string, mixed> | null
     */
    #[Url(as: 'filters')]
    public ?array $tableFilters = null;

    public static function canAccess(): bool
    {
        return static::jenisTersediaUntuk(auth()->user()?->role) !== [];
    }

    // =====================================================================
    // JENIS RIWAYAT
    // =====================================================================

    /**
     * Jenis riwayat yang boleh dilihat suatu peran.
     *
     * @return array<string, array{label:string, ikon:string, ringkas:string}>
     */
    public static function jenisTersediaUntuk(?string $role): array
    {
        $semua = [
            'permintaan' => [
                'label'   => 'Riwayat Permintaan Barang',
                'ikon'    => 'heroicon-m-clipboard-document-check',
                'ringkas' => 'Permintaan Barang',
                'peran'   => ['admin', 'kasubbag', 'petugas_gudang', 'ketua_tim', 'tim'],
            ],
            'mutasi_stok' => [
                'label'   => 'Riwayat Mutasi Stok',
                'ikon'    => 'heroicon-m-arrows-right-left',
                'ringkas' => 'Mutasi Stok',
                'peran'   => ['admin', 'kasubbag', 'petugas_gudang'],
            ],
            'mutasi_aset' => [
                'label'   => 'Riwayat Mutasi Aset',
                'ikon'    => 'heroicon-m-truck',
                'ringkas' => 'Mutasi Aset',
                'peran'   => ['kasubbag', 'petugas_gudang', 'ketua_tim'],
            ],
        ];

        return collect($semua)
            ->filter(fn (array $j) => in_array($role, $j['peran'], true))
            ->map(fn (array $j) => Arr::except($j, 'peran'))
            ->all();
    }

    /** @return array<string, array{label:string, ikon:string, ringkas:string}> */
    public function getJenisTersediaProperty(): array
    {
        return static::jenisTersediaUntuk(auth()->user()?->role);
    }

    /** Jenis yang sedang aktif, selalu bernilai sah bagi peran pengguna. */
    public function jenisAktif(): string
    {
        $tersedia = $this->jenisTersedia;

        return isset($tersedia[$this->jenis])
            ? $this->jenis
            : (string) array_key_first($tersedia);
    }

    public function pilihJenis(string $jenis): void
    {
        if (! isset($this->jenisTersedia[$jenis])) {
            return;
        }

        $this->jenis = $jenis;

        // Penyaring tiap jenis berbeda, sehingga sisa penyaring jenis
        // sebelumnya dibersihkan agar tidak diterapkan pada kueri yang salah.
        $this->tableFilters = null;
        $this->resetTableFiltersForm();
        $this->resetPage();
    }

    // =====================================================================
    // TABEL
    // =====================================================================

    public function table(Table $table): Table
    {
        return match ($this->jenisAktif()) {
            'mutasi_stok' => $this->tabelMutasiStok($table),
            'mutasi_aset' => $this->tabelMutasiAset($table),
            default       => $this->tabelPermintaan($table),
        };
    }

    /** Permintaan barang yang sudah mencapai status akhir. */
    protected function tabelPermintaan(Table $table): Table
    {
        return $table
            ->query(fn () => PermintaanBarangResource::getEloquentQuery()
                ->whereIn('status', PermintaanBarang::STATUS_RIWAYAT))
            ->deferFilters(false)
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn ($record) => PermintaanBarangResource::getUrl('detail', ['record' => $record]))
            ->columns([
                TextColumn::make('kode_permintaan')->label('Kode')->searchable()->sortable(),
                TextColumn::make('tim.nama_tim')->label('Tim Pemohon')->searchable()->sortable(),
                TextColumn::make('nama_pemohon')->label('Pemohon')->searchable(),
                TextColumn::make('detail_count')->label('Item')->counts('detail')->alignEnd(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => PermintaanBarang::STATUS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'selesai'                                         => 'success',
                        'ditolak_ketua', 'ditolak_kasubbag', 'bermasalah' => 'danger',
                        default                                           => 'gray',
                    }),
                TextColumn::make('created_at')->label('Diajukan')->dateTime('d-m-Y H:i')->sortable(),
                TextColumn::make('pengesahan_at')->label('Disahkan')->dateTime('d-m-Y H:i')->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->multiple()
                    ->options(collect(PermintaanBarang::STATUS)
                        ->only(PermintaanBarang::STATUS_RIWAYAT)
                        ->all()),

                SelectFilter::make('tim_pemohon_id')
                    ->label('Tim Pemohon')
                    ->relationship('tim', 'nama_tim')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => ! in_array(auth()->user()?->role, ['tim', 'ketua_tim'])),

                $this->penyaringPeriode('created_at', 'Tanggal Pengajuan'),
            ]);
    }

    /** Buku besar pergerakan stok (kartu kendali seluruh barang). */
    protected function tabelMutasiStok(Table $table): Table
    {
        return $table
            ->query(fn () => MutasiStok::query()->with(['barang.kategori', 'petugas']))
            ->deferFilters(false)
            ->defaultSort('tanggal', 'desc')
            ->columns([
                TextColumn::make('tanggal')->label('Tanggal')->date('d-m-Y')->sortable(),
                TextColumn::make('barang.nama_barang')
                    ->label('Barang')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->barang?->kategori?->nama_kategori),
                TextColumn::make('jenis')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color(fn ($state) => match ($state) {
                        'masuk'  => 'success',
                        'keluar' => 'danger',
                        default  => 'warning',
                    }),
                TextColumn::make('sumber')
                    ->label('Uraian')
                    ->formatStateUsing(fn ($state) => $state ? ucwords(str_replace('_', ' ', $state)) : '—'),
                TextColumn::make('jumlah')
                    ->label('Jumlah')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => ($state > 0 ? '+' : '') . $state . ' ' . ($record->barang?->satuan ?? '')),
                TextColumn::make('saldo_sesudah')->label('Saldo')->alignEnd()->sortable(),
                TextColumn::make('nomor_dasar')->label('Nomor Dasar')->searchable()->placeholder('—'),
                TextColumn::make('petugas.name')->label('Petugas')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('jenis')
                    ->label('Jenis')
                    ->options(['masuk' => 'Masuk', 'keluar' => 'Keluar', 'koreksi' => 'Koreksi']),

                SelectFilter::make('sumber')
                    ->label('Uraian')
                    ->options([
                        'pembelian'          => 'Pembelian',
                        'transfer_masuk'     => 'Transfer Masuk',
                        'stok_awal'          => 'Stok Awal',
                        'pemakaian'          => 'Pemakaian',
                        'pengembalian'       => 'Pengembalian',
                        'reklasifikasi_aset' => 'Reklasifikasi Aset',
                        'stok_opname'        => 'Stok Opname',
                    ]),

                SelectFilter::make('barang_id')
                    ->label('Barang')
                    ->relationship('barang', 'nama_barang')
                    ->searchable()
                    ->preload(),

                $this->penyaringPeriode('tanggal', 'Tanggal Mutasi'),
            ]);
    }

    /** Serah terima mutasi aset tetap yang sudah disahkan. */
    protected function tabelMutasiAset(Table $table): Table
    {
        return $table
            ->query(fn () => BastMutasiAsetResource::getEloquentQuery()
                ->whereNotNull('disahkan_at'))
            ->deferFilters(false)
            ->defaultSort('disahkan_at', 'desc')
            ->columns([
                TextColumn::make('nomor_bast')->label('Nomor BAST')->searchable()->sortable(),
                TextColumn::make('aset.nama_aset')
                    ->label('Aset')
                    ->searchable()
                    ->description(fn ($record) => $record->aset?->nup),
                TextColumn::make('timAsal.nama_tim')->label('Tim Asal')->placeholder('—'),
                TextColumn::make('timTujuan.nama_tim')->label('Tim Tujuan')->placeholder('—'),
                TextColumn::make('tanggal_mutasi')->label('Tanggal Mutasi')->date('d-m-Y')->sortable(),
                TextColumn::make('disahkan_at')->label('Disahkan')->dateTime('d-m-Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('tim_tujuan_id')
                    ->label('Tim Tujuan')
                    ->relationship('timTujuan', 'nama_tim')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()?->role !== 'ketua_tim'),

                $this->penyaringPeriode('tanggal_mutasi', 'Tanggal Mutasi'),
            ]);
    }

    /** Penyaring rentang tanggal yang dipakai seluruh jenis riwayat. */
    protected function penyaringPeriode(string $kolom, string $label): Filter
    {
        return Filter::make('periode')
            ->label($label)
            ->schema([
                DatePicker::make('dari')->label('Dari tanggal')->native(false),
                DatePicker::make('sampai')->label('Sampai tanggal')->native(false),
            ])
            ->columns(2)
            ->query(fn (Builder $query, array $data) => $query
                ->when($data['dari'] ?? null, fn (Builder $q, $t) => $q->whereDate($kolom, '>=', $t))
                ->when($data['sampai'] ?? null, fn (Builder $q, $t) => $q->whereDate($kolom, '<=', $t)))
            ->indicateUsing(function (array $data) use ($label): ?string {
                $dari   = $data['dari'] ?? null;
                $sampai = $data['sampai'] ?? null;

                return match (true) {
                    $dari && $sampai => $label . ': ' . $dari . ' s.d. ' . $sampai,
                    (bool) $dari     => $label . ' sejak ' . $dari,
                    (bool) $sampai   => $label . ' sampai ' . $sampai,
                    default          => null,
                };
            });
    }
}
