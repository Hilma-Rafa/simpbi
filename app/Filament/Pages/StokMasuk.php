<?php

namespace App\Filament\Pages;

use App\Filament\Support\AksiImpor;
use App\Models\BarangPersediaan;
use App\Models\Kategori;
use App\Models\MutasiStok;
use App\Services\Impor\ImporStokMasuk;
use App\Services\StokService;
use App\Support\KodeBarang;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

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

    /**
     * Sumber yang wajib bernomor dasar: pembelian punya nomor dokumen
     * pengadaan dan transfer masuk punya nomor berita acaranya. Dipakai
     * formulir Catat Stok Masuk dan Impor Stok Masuk, supaya keduanya tidak
     * pernah berbeda aturan.
     */
    public const SUMBER_WAJIB_NOMOR_DASAR = ['pembelian', 'transfer_masuk'];

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
                    ->date('d-m-Y')
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

            /*
             * Impor Stok Masuk (menggantikan Impor Stok Awal). Jenis transaksi
             * dipilih sekali di pop-up dan berlaku untuk seluruh berkas; tanggal,
             * nomor dasar, dan keterangan ikut per baris pada template. Memakai
             * AksiImpor yang sama dengan impor data induk, dan hak aksesnya ikut
             * halaman ini: hanya Petugas Gudang. Setiap baris dicatat lewat
             * StokService::tambah() — jalur yang sama dengan Catat Stok Masuk.
             */
            AksiImpor::buat(
                judul: ImporStokMasuk::JUDUL,
                kolom: ImporStokMasuk::kolom(),
                namaTemplate: 'Template-Impor-Stok-Masuk.xlsx',
                impor: fn (string $lintasan, array $data) => app(ImporStokMasuk::class)->jalankan(
                    $lintasan,
                    (string) $data['jenis'],
                    (int) auth()->id(),
                ),
                label: 'Impor Stok Masuk',
                isian: [
                    Select::make('jenis')
                        ->label('Jenis Transaksi Stok Masuk')
                        ->options(self::SUMBER_MASUK)
                        ->native(false)
                        ->required()
                        ->live(),
                    // Gaya peringatan yang sama dengan mode peragaan di Pengaturan.
                    Callout::make('Jenis Stok Awal')
                        ->description(new HtmlString('<ul class="list-disc ps-5 space-y-1">'
                            . implode('', array_map(fn (string $c) => '<li>' . e($c) . '</li>', ImporStokMasuk::CATATAN_STOK_AWAL))
                            . '</ul>'))
                        ->warning()
                        ->visible(fn (Get $get): bool => $get('jenis') === 'stok_awal'),
                    Text::make('Kolom Tanggal wajib diisi pada setiap baris berkas.')
                        ->color('gray')
                        ->visible(fn (Get $get): bool => filled($get('jenis')) && $get('jenis') !== 'stok_awal'),
                ],
                petunjuk: ImporStokMasuk::petunjuk(),
            ),

            Action::make('catat')
                ->label('Catat Stok Masuk')
                ->icon('heroicon-m-plus')
                ->modalHeading('Catat Stok Masuk')
                ->modalDescription('Satu dokumen dapat memuat banyak barang sekaligus.')
                ->modalSubmitActionLabel('Simpan')
                ->modalCancelActionLabel('Batal')
                ->modalWidth(Width::FourExtraLarge)
                ->schema([
                    /*
                     * Sumber, nomor dokumen, dan tanggal berlaku untuk seluruh
                     * barang di dalam satu nota, sehingga diisi sekali saja.
                     * Sebelumnya ketiganya diketik ulang untuk setiap barang —
                     * satu nota berisi sepuluh jenis barang berarti tiga puluh
                     * isian yang isinya sama persis, dan setiap pengulangan
                     * adalah satu kesempatan salah ketik yang membuat baris
                     * yang seharusnya sekelompok jadi tampak tidak berhubungan
                     * pada kartu kendali.
                     */
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
                            self::SUMBER_WAJIB_NOMOR_DASAR,
                            true,
                        ))
                        ->helperText(function (Get $get): string {
                            $nomor = trim((string) $get('nomor_dasar'));

                            /*
                             * Nota yang tercatat dua kali adalah kekeliruan
                             * yang paling mungkin terjadi pada pemasukan
                             * borongan, sekaligus paling sulit ditemukan
                             * sesudahnya. Peringatan ini tidak menghalangi:
                             * nomor yang sama bisa saja sah, misalnya ketika
                             * sebagian barang pada satu nota baru menyusul.
                             */
                            if ($nomor !== '' && MutasiStok::where('nomor_dasar', $nomor)->exists()) {
                                return 'Nomor ini sudah pernah tercatat. Pastikan bukan pemasukan yang terulang.';
                            }

                            return in_array($get('sumber'), self::SUMBER_WAJIB_NOMOR_DASAR, true)
                                ? 'Nomor dokumen pengadaan atau berita acara serah terima.'
                                : 'Nomor dokumen, bila ada.';
                        })
                        ->live(onBlur: true)
                        // Mengikuti lebar kolom nomor_dasar pada tabel mutasi_stok
                        ->maxLength(60),

                    /*
                     * Tanggal dokumen, bukan tanggal pencatatan.
                     *
                     * Kolom "Tanggal M/K" pada kartu kendali menunjuk tanggal
                     * faktur atau berita acaranya, sedangkan dokumen kerap baru
                     * sampai ke gudang beberapa hari kemudian. Tanggal mundur
                     * diterima: saldo seluruh barang dihitung ulang setiap buku
                     * besarnya berubah, sehingga kolom Sisa tetap benar meski
                     * transaksinya disisipkan di tengah.
                     */
                    DatePicker::make('tanggal')
                        ->label('Tanggal Dokumen')
                        ->native(false)
                        ->displayFormat('d-m-Y')
                        ->default(now())
                        ->required()
                        // Transaksi tidak dapat dicatat mendahului kejadiannya
                        ->maxDate(now())
                        ->helperText('Tanggal pada faktur atau berita acara, bukan tanggal pencatatan.'),

                    Repeater::make('barang')
                        ->label('Barang')
                        ->addActionLabel('Tambah barang')
                        ->defaultItems(1)
                        ->minItems(1)
                        /*
                         * Urutan baris di dalam satu nota tidak membawa arti
                         * apa pun: seluruhnya bertanggal sama, dan kartu
                         * kendali mengurutkan transaksinya sendiri menurut
                         * tanggal. Pegangan seret bawaan komponen justru
                         * membuat urutannya tampak penting.
                         */
                        ->reorderable(false)
                        /*
                         * Baris terakhir tidak dapat dihapus. Tanpa ini daftar
                         * barang bisa dikosongkan sama sekali, dan kekeliruan
                         * itu baru ditegur ketika Simpan ditekan — padahal yang
                         * dimaksud pengguna hampir selalu mengganti isi
                         * barisnya, bukan meniadakan barangnya. `minItems`
                         * tetap dipertahankan sebagai pengaman terakhir.
                         */
                        ->deletable(fn (Repeater $component): bool => count($component->getRawState()) > 1)
                        ->columns(12)
                        ->columnSpanFull()
                        ->schema([
                            Select::make('barang_id')
                                ->label('Barang')
                                ->options(fn () => BarangPersediaan::query()
                                    ->where('status_aktif', true)
                                    ->orderBy('nama_barang')
                                    ->pluck('nama_barang', 'id'))
                                ->searchable()
                                ->required()
                                /*
                                 * Satu barang tidak boleh muncul dua kali dalam
                                 * satu nota. Dua baris terpisah untuk barang
                                 * yang sama menghasilkan dua transaksi bernomor
                                 * dasar dan bertanggal sama pada kartu
                                 * kendalinya, yang kemudian mustahil dibedakan
                                 * — dan tampak persis seperti pencatatan ganda.
                                 */
                                ->distinct()
                                ->createOptionForm(self::borangBarangBaru())
                                ->createOptionUsing(fn (array $data): int => static::simpanBarangBaru($data))
                                ->createOptionModalHeading('Barang Baru')
                                ->columnSpan(6),

                            TextInput::make('jumlah')
                                ->label('Jumlah')
                                ->numeric()
                                ->minValue(1)
                                ->required()
                                ->columnSpan(2),

                            TextInput::make('keterangan')
                                ->label('Keterangan')
                                ->maxLength(255)
                                ->columnSpan(4),
                        ]),
                ])
                /*
                 * Dialog konfirmasi didaftarkan sebagai aksi anak, bukan aksi
                 * halaman: Filament hanya mau memasang aksi bersarang yang
                 * dikenali induknya, dan justru itulah yang menjaga dialog ini
                 * tidak bisa dipanggil sendirian dari luar formulirnya.
                 */
                ->registerModalActions([
                    $this->konfirmasiCatatAction(),
                ])
                ->action(function (array $data, StokMasuk $livewire): void {
                    /*
                     * Formulir yang lolos validasi belum mencatat apa pun.
                     * Dialog konfirmasi ditumpuk di atasnya — formulir tetap
                     * terpasang di bawahnya — sehingga "Batal" mengembalikan
                     * pengguna ke isian yang masih utuh, dan buku besar stok
                     * baru berubah setelah "Ya, Simpan" ditekan.
                     */
                    $livewire->mountAction('konfirmasiCatat', ['nota' => $data]);
                }),
        ];
    }

    /**
     * Dialog konfirmasi sebelum nota tercatat.
     *
     * Ditulis sebagai aksi tersendiri, bukan `requiresConfirmation()` pada aksi
     * Catat, sebab pilihan itu hanya mengganti ikon dan label pada modal yang
     * sama — formulirnya tetap menjadi satu-satunya langkah. Yang dibutuhkan di
     * sini justru langkah kedua: stok masuk mengubah buku besar dan tidak dapat
     * dibatalkan dari layar mana pun, sehingga satu jeda untuk membaca ulang
     * lebih murah daripada satu koreksi sesudahnya.
     */
    public function konfirmasiCatatAction(): Action
    {
        return Action::make('konfirmasiCatat')
            ->modalHeading('Apakah data barang sudah sesuai?')
            ->modalWidth(Width::Medium)
            ->modalContent(fn (array $arguments) => view(
                'filament.partials.konfirmasi-stok-masuk',
                ['ringkasan' => $this->ringkasanNota($arguments['nota'])],
            ))
            ->modalSubmitActionLabel('Ya, Simpan')
            ->modalCancelActionLabel('Batal')
            // Nota sudah tercatat, sehingga formulir di bawahnya ikut ditutup;
            // membiarkannya terbuka mengundang nota yang sama disimpan dua kali.
            ->cancelParentActions()
            ->action(fn (array $arguments) => $this->simpanNota($arguments['nota']));
    }

    /**
     * Ringkasan nota untuk dibaca ulang di dialog konfirmasi.
     *
     * Seluruh angkanya diturunkan dari isian formulir yang baru saja lolos
     * validasi, bukan dibaca dari basis data — pada titik ini memang belum ada
     * satu baris pun yang tersimpan di sana.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,string>
     */
    protected function ringkasanNota(array $data): array
    {
        $baris = $data['barang'] ?? [];

        return [
            'Nota'          => filled($data['nomor_dasar'] ?? null) ? $data['nomor_dasar'] : '—',
            'Sumber'        => self::SUMBER_MASUK[$data['sumber'] ?? null] ?? '—',
            'Tanggal'       => Carbon::parse($data['tanggal'])->format('d-m-Y'),
            'Jumlah barang' => count($baris) . ' barang',
            'Total unit'    => collect($baris)->sum(fn (array $b): int => (int) ($b['jumlah'] ?? 0)) . ' unit',
        ];
    }

    /**
     * Mencatat satu nota ke buku besar stok.
     *
     * @param  array<string,mixed>  $data
     */
    protected function simpanNota(array $data): void
    {
        $stok = app(StokService::class);

        /*
         * Seluruh baris satu nota disimpan dalam satu transaksi. Sebuah nota
         * adalah satu kejadian; tercatat separuh — misalnya karena satu barang
         * membuat saldonya mustahil — meninggalkan stok yang tidak sesuai
         * dokumen mana pun, dan itu sulit ditemukan justru karena sebagiannya
         * tampak benar.
         */
        DB::transaction(function () use ($data, $stok): void {
            foreach ($data['barang'] as $baris) {
                $stok->tambah(
                    barangId: (int) $baris['barang_id'],
                    jumlah: (int) $baris['jumlah'],
                    sumber: $data['sumber'],
                    nomorDasar: $data['nomor_dasar'] ?? null,
                    keterangan: $baris['keterangan'] ?? null,
                    petugasId: auth()->id(),
                    tanggal: $data['tanggal'] ?? null,
                );
            }
        });

        $jumlahBaris = count($data['barang']);

        Notification::make()
            ->title('Stok masuk tercatat')
            ->body($jumlahBaris === 1
                ? 'Satu barang tercatat pada kartu kendalinya.'
                : $jumlahBaris . ' barang tercatat pada kartu kendalinya masing-masing.')
            ->success()
            ->send();
    }

    /** Apakah kode itu (tanpa spasi ujung) sudah dipakai barang lain pada kategori yang sama. */
    public static function kodeBarangSudahDipakai(int $kategoriId, string $kode): bool
    {
        return BarangPersediaan::where('kategori_id', $kategoriId)
            ->where('kode_barang', trim($kode))
            ->exists();
    }

    /**
     * Membuat Barang Persediaan dari dialog Barang Baru. Pelanggaran UNIQUE yang
     * lolos dari aturan form (mis. dua pembuatan bersamaan) berujung pesan yang
     * sama dengan aturan itu, bukan galat basis data; dialog tetap terbuka.
     */
    public static function simpanBarangBaru(array $data): int
    {
        try {
            return BarangPersediaan::create([
                ...$data,
                'kode_barang'  => trim((string) ($data['kode_barang'] ?? '')),
                // Barang baru selalu lahir berstok nol;
                // isinya datang dari nota yang sedang
                // dicatat ini, bukan diketik langsung.
                'stok_fisik'   => 0,
                'stok_hold'    => 0,
                'status_aktif' => true,
            ])->id;
        } catch (UniqueConstraintViolationException) {
            Notification::make()
                ->title('Kode barang sudah dipakai pada kategori ini.')
                ->danger()
                ->send();

            throw new Halt;
        }
    }

    /**
     * Borang barang baru, dipakai ketika barang yang diterima belum ada di
     * katalog.
     *
     * Disediakan di dalam formulir Stok Masuk, bukan dengan menyuruh petugas
     * membuka halaman Barang Persediaan lebih dulu, sebab barang baru justru
     * paling sering ketahuan pada saat notanya dibuka. Memaksa keluar dari
     * formulir berarti seluruh baris yang sudah diketik harus diulang.
     *
     * Stok fisik tidak diminta di sini: barang baru lahir berstok nol dan
     * isinya datang dari nota yang sedang dicatat, sehingga setiap angka stok
     * tetap punya baris kartu kendalinya sendiri.
     *
     * @return array<int,mixed>
     */
    protected static function borangBarangBaru(): array
    {
        return [
            Select::make('kategori_id')
                ->label('Kategori')
                ->options(fn () => Kategori::where('tipe', 'persediaan')
                    ->orderBy('nama_kategori')
                    ->pluck('nama_kategori', 'id'))
                ->searchable()
                ->native(false)
                ->required(),

            TextInput::make('kode_barang')
                ->label('Kode Barang')
                ->required()
                // Aturan dan bentuk isian sama dengan form Barang Persediaan
                // (KodeBarang), supaya barang yang lahir dari nota tidak memakai
                // ukuran kode yang lain dari barang yang lahir dari katalog.
                ->mask('999999')
                ->inputMode('numeric')
                ->placeholder(KodeBarang::CONTOH)
                ->regex(KodeBarang::POLA)
                ->validationMessages(['regex' => KodeBarang::PESAN])
                ->rule('bail')
                // Spasi di ujung dipangkas sebelum divalidasi, disimpan, dan
                // dibandingkan: MySQL mengabaikannya pada pembanding unik
                // sedangkan SQLite tidak. Memakai trim(), bukan
                // dehydrateStateUsing(), karena yang terakhir baru berjalan
                // sesudah validasi — " 000100 " akan tertolak aturan enam digit
                // padahal setelah dipangkas sah.
                ->trim()
                ->rule(fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($get): void {
                    if (static::kodeBarangSudahDipakai((int) $get('kategori_id'), (string) $value)) {
                        $fail('Kode barang sudah dipakai pada kategori ini.');
                    }
                })
                ->helperText(KodeBarang::BANTUAN),

            TextInput::make('nama_barang')
                ->label('Nama Barang')
                ->required()
                ->maxLength(150),

            TextInput::make('satuan')
                ->label('Satuan')
                ->required()
                ->maxLength(20)
                ->placeholder('Pcs, Dus, Lusin, Rim'),

            TextInput::make('stok_minimum')
                ->label('Stok Minimum')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required()
                ->helperText('Nol berarti tidak dipantau; barang ini tidak akan memicu peringatan stok menipis.'),
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
