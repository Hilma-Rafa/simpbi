<?php

namespace App\Filament\Pages;

use App\Models\BarangPersediaan;
use App\Models\MutasiStok;
use App\Services\StokService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

/**
 * Pencatatan stok masuk barang persediaan (UC-07).
 *
 * Halaman menampilkan riwayat transaksi masuk terbaru dan menyediakan aksi
 * untuk mencatat penambahan stok. Seluruh perubahan stok dilakukan melalui
 * StokService agar pencatatan kartu kendali (mutasi_stok) tetap konsisten.
 * Hanya Petugas Gudang yang berwenang (Instruksi §4).
 */
class StokMasuk extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.stok-masuk';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|\UnitEnum|null $navigationGroup = 'Persediaan';

    protected static ?string $navigationLabel = 'Stok Masuk';

    protected static ?string $title = 'Stok Masuk';

    protected static ?int $navigationSort = 3;

    /** Sumber yang relevan untuk transaksi masuk (Instruksi §22). */
    public const SUMBER_MASUK = [
        'pembelian'      => 'Pembelian',
        'transfer_masuk' => 'Transfer Masuk',
        'stok_awal'      => 'Stok Awal',
        'pengembalian'   => 'Pengembalian',
    ];

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'petugas_gudang';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                MutasiStok::query()
                    ->where('jenis', 'masuk')
                    ->with(['barang', 'petugas'])
                    ->latest('tanggal')
                    ->latest('id')
            )
            ->columns([
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('barang.nama_barang')
                    ->label('Barang')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('jumlah')
                    ->label('Jumlah')
                    ->numeric()
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, $record) => '+' . $state . ' ' . ($record->barang?->satuan ?? '')),
                TextColumn::make('sumber')
                    ->label('Sumber')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => self::SUMBER_MASUK[$state] ?? ($state ? ucfirst($state) : '—'))
                    ->color('info'),
                TextColumn::make('nomor_dasar')
                    ->label('Nomor Dasar')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('saldo_sesudah')
                    ->label('Saldo Setelah')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('petugas.name')
                    ->label('Petugas')
                    ->toggleable(),
            ])
            ->emptyStateHeading('Belum ada stok masuk')
            ->emptyStateDescription('Belum ada transaksi penambahan stok yang tercatat.')
            ->emptyStateIcon('heroicon-o-inbox');
    }

    protected function getHeaderActions(): array
    {
        return [
            // Pintasan ke halaman Riwayat dengan jenis Mutasi Stok terpilih
            Action::make('riwayat')
                ->label('Riwayat')
                ->icon('heroicon-m-archive-box')
                ->iconPosition(\Filament\Support\Enums\IconPosition::Before)
                ->color('gray')
                ->outlined()
                ->tooltip('Lihat seluruh pergerakan stok, masuk maupun keluar')
                ->url(\App\Filament\Pages\Riwayat::getUrl(['jenis' => 'mutasi_stok']))
                ->visible(fn () => \App\Filament\Pages\Riwayat::canAccess()),

            Action::make('catat')
                ->label('Catat Stok Masuk')
                ->icon('heroicon-m-plus')
                ->modalHeading('Catat Stok Masuk')
                ->modalSubmitActionLabel('Simpan')
                ->schema([
                    Select::make('barang_id')
                        ->label('Barang')
                        ->options(fn () => BarangPersediaan::query()
                            ->where('status_aktif', true)
                            ->orderBy('nama_barang')
                            ->pluck('nama_barang', 'id'))
                        ->searchable()
                        // Batas tanggal dokumen bergantung pada barang yang
                        // dipilih, sehingga pilihannya harus langsung terkirim
                        ->live()
                        ->required(),
                    TextInput::make('jumlah')
                        ->label('Jumlah')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                    Select::make('sumber')
                        ->label('Sumber')
                        ->options(self::SUMBER_MASUK)
                        ->native(false)
                        ->live()
                        ->required(),
                    TextInput::make('nomor_dasar')
                        ->label('Nomor Dasar')
                        /*
                         * Diwajibkan hanya untuk sumber yang memang lahir dari
                         * dokumen: pembelian punya nomor dokumen pengadaan dan
                         * transfer masuk punya nomor berita acaranya. Tanpa
                         * kewajiban ini, kolom "Nomor Dasar M/K" pada kartu
                         * kendali terbit kosong dan transaksinya tidak dapat
                         * ditelusuri ke bukti mana pun.
                         *
                         * Saldo pembuka dan pengembalian sengaja tidak
                         * diwajibkan, sebab keduanya kerap tidak punya dokumen
                         * — sama seperti pada kartu kendali manualnya, yang
                         * juga membiarkan kolom itu kosong untuk stok awal.
                         */
                        ->required(fn (Get $get): bool => in_array(
                            $get('sumber'),
                            ['pembelian', 'transfer_masuk'],
                            true,
                        ))
                        ->helperText(fn (Get $get): string => in_array($get('sumber'), ['pembelian', 'transfer_masuk'], true)
                            ? 'Nomor dokumen pengadaan atau berita acara serah terima.'
                            : 'Nomor dokumen, bila ada.')
                        // Mengikuti lebar kolom nomor_dasar pada tabel mutasi_stok
                        ->maxLength(60),
                    /*
                     * Tanggal dokumen, bukan tanggal pencatatan.
                     *
                     * Kolom "Tanggal M/K" pada kartu kendali menunjuk tanggal
                     * faktur atau berita acaranya, sedangkan dokumen kerap baru
                     * sampai ke gudang beberapa hari kemudian. Sebelumnya kartu
                     * selalu memakai tanggal input, sehingga hasil cetak sistem
                     * tidak dapat disandingkan dengan arsip dokumen aslinya.
                     */
                    DatePicker::make('tanggal')
                        ->label('Tanggal Dokumen')
                        ->native(false)
                        ->displayFormat('d-m-Y')
                        ->default(now())
                        ->required()
                        // Transaksi tidak dapat dicatat mendahului kejadiannya
                        ->maxDate(now())
                        /*
                         * Tidak boleh mendahului transaksi terakhir barang itu.
                         * Kolom "Sisa" dicetak apa adanya dari saldo yang
                         * terekam saat transaksi dijalankan, sementara kartunya
                         * diurutkan menurut tanggal; tanggal yang melompat ke
                         * belakang akan memisahkan kedua urutan itu sehingga
                         * kolom Sisa terbaca naik-turun tanpa sebab.
                         */
                        ->minDate(fn (Get $get): ?string => self::batasTanggal($get('barang_id')))
                        ->helperText(function (Get $get): string {
                            $batas = self::batasTanggal($get('barang_id'));

                            return $batas
                                ? 'Tanggal pada faktur atau berita acara. Barang ini terakhir bermutasi '
                                    . Carbon::parse($batas)->translatedFormat('j F Y')
                                    . ', jadi tanggalnya tidak dapat lebih awal daripada itu.'
                                : 'Tanggal pada faktur atau berita acara, bukan tanggal pencatatan.';
                        }),
                    Textarea::make('keterangan')
                        ->label('Keterangan')
                        ->rows(2)
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
                    app(StokService::class)->tambah(
                        barangId: (int) $data['barang_id'],
                        jumlah: (int) $data['jumlah'],
                        sumber: $data['sumber'],
                        nomorDasar: $data['nomor_dasar'] ?? null,
                        keterangan: $data['keterangan'] ?? null,
                        petugasId: auth()->id(),
                        tanggal: $data['tanggal'] ?? null,
                    );

                    Notification::make()
                        ->title('Stok masuk tercatat')
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * Tanggal paling awal yang boleh dipakai untuk suatu barang.
     *
     * Nilainya diambil dari StokService, bukan dihitung ulang di sini, supaya
     * batas yang ditawarkan formulir tidak mungkin berbeda dengan batas yang
     * ditegakkan ketika transaksinya disimpan.
     */
    protected static function batasTanggal(mixed $barangId): ?string
    {
        return $barangId
            ? StokService::tanggalMutasiTerakhir((int) $barangId)
            : null;
    }
}
