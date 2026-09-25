<?php

namespace App\Filament\Resources\PermintaanBarangs;

use App\Filament\Pages\Riwayat;
use App\Filament\Resources\PermintaanBarangs\Pages;
use App\Filament\Support\GayaUnduh;
use App\Filament\Support\KeadaanKosong;
use App\Models\User;
use App\Support\TandaTangan;
use App\Support\TindakanPermintaan;
use Illuminate\Support\HtmlString;
use App\Models\PermintaanBarang;
use App\Models\Tim;
use App\Support\JamKerja;
use App\Models\RiwayatPersetujuan;
use App\Services\StokService;
use App\Services\DokumenPermintaanService;
use App\Services\NotifikasiService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\View;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use BackedEnum;

class PermintaanBarangResource extends Resource
{
    protected static ?string $model = PermintaanBarang::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Permintaan & Distribusi';
    protected static ?string $navigationLabel = 'Permintaan Barang';
    protected static ?string $modelLabel = 'Permintaan Barang';
    protected static ?string $pluralModelLabel = 'Permintaan Barang';
    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['tim', 'ketua_tim', 'petugas_gudang', 'kasubbag', 'admin']);
    }

    public static function canCreate(): bool
    {
        return false; // pengajuan dilakukan melalui halaman Katalog Barang
    }

    /**
     * Pembatasan data menurut peran pengguna.
     * Tim dan Ketua Tim hanya melihat permintaan timnya sendiri, sedangkan
     * Petugas Gudang dan Kasubbag Umum melihat seluruh permintaan.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['tim', 'pengaju', 'detail.barang']);
        $user  = auth()->user();

        if (in_array($user->role, ['tim', 'ketua_tim'])) {
            $query->where('tim_pemohon_id', $user->tim_id);
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            // Daftar ini hanya memuat permintaan yang masih berjalan.
            // Permintaan yang sudah berstatus akhir pindah tampilannya ke
            // halaman Riwayat, tanpa dipindahkan atau dihapus dari basis data.
            // Pembatasan diletakkan pada tabel, bukan pada getEloquentQuery(),
            // agar halaman Riwayat, widget dasbor, dan halaman rincian tetap
            // dapat membaca seluruh permintaan memakai cakupan peran yang sama.
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn(
                'status',
                PermintaanBarang::STATUS_RIWAYAT
            ))
            // Penyaring langsung diterapkan. Bawaan Filament menundanya
            // sampai tombol "Terapkan filter" ditekan, sehingga tautan
            // bertanda penyaring dari dasbor tampak tidak bekerja.
            ->deferFilters(false)
            ->defaultSort('created_at', 'desc')
            /** Lihat catatan yang sama pada tabel Barang Persediaan. */
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->emptyStateHeading(fn ($livewire): string => KeadaanKosong::sedangDisaring($livewire)
                ? 'Tidak ada permintaan yang cocok'
                : 'Belum ada permintaan barang')
            ->emptyStateDescription(fn ($livewire): string => KeadaanKosong::sedangDisaring($livewire)
                ? 'Coba longgarkan penyaringnya, atau periksa kembali ejaan kata yang dicari.'
                : 'Permintaan yang diajukan tim kerja akan muncul di sini, lengkap dengan tahap yang sedang berjalan.')
            ->columns([
                TextColumn::make('kode_permintaan')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),

                // Akronim resmi dipakai bila tim memilikinya, supaya kolom ini
                // tidak melebarkan tabel sampai kolom Batas Waktu terdorong ke
                // luar layar. Pencarian tetap mengenai kolom aslinya, sehingga
                // mengetik "Pertambangan" tetap menemukan tim PEK.
                TextColumn::make('tim.nama_tim')
                    ->label('Tim Pemohon')
                    ->formatStateUsing(fn ($state) => Tim::ringkas($state))
                    ->tooltip(fn ($state) => Tim::ringkas($state) === $state ? null : $state)
                    ->searchable(),

                // Pemohon disembunyikan secara bawaan. Namanya sudah tampil
                // pada dialog rincian, sedangkan di tabel kolom ini hanya
                // menambah lebar tanpa membantu pembacaan sekilas — pembaca
                // daftar mencari kode, tahap, dan batas waktu lebih dulu.
                // Pengguna yang membutuhkannya tetap dapat memunculkannya
                // kembali lewat tombol pengatur kolom.
                TextColumn::make('nama_pemohon')
                    ->label('Pemohon')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('detail_count')
                    ->label('Jumlah Item')
                    ->counts('detail'),

                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->date('d-m-Y'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    // Label pendek agar tabel muat satu layar; penyebutan
                    // resmi tetap dapat dibaca lewat tetikus.
                    ->formatStateUsing(fn ($state) => PermintaanBarang::labelRingkas($state))
                    ->tooltip(fn ($state) => PermintaanBarang::STATUS[$state] ?? null)
                    ->color(fn ($state) => match ($state) {
                        'selesai'                           => 'success',
                        // "Bermasalah" memakai warna yang sama dengan halaman
                        // Riwayat dan dengan KPI pada dasbor Petugas Gudang.
                        // Sebelumnya kelabu di sini dan merah di sana, padahal
                        // statusnya satu dan sama-sama menuntut perhatian.
                        'ditolak_ketua', 'ditolak_kasubbag', 'bermasalah' => 'danger',
                        'kedaluwarsa'                       => 'gray',
                        'siap_diambil'                      => 'info',
                        'menunggu_pengesahan'               => 'info',
                        default                             => 'warning',
                    }),

                TextColumn::make('hold_expired_at')
                    ->label('Batas Waktu')
                    ->dateTime('d-m-Y H:i')
                    ->description(fn ($record) => $record->hold_expired_at
                        ? $record->hold_expired_at->diffForHumans()
                        : null)
                    ->placeholder('—'),
            ])
            ->filters([
                Filter::make('kode_permintaan')
                    ->label('Kode Permintaan')
                    ->schema([
                        TextInput::make('value')
                            ->label('Kode Permintaan')
                            ->placeholder('Masukkan kode permintaan...')
                            ->maxLength(50),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            filled($data['value'] ?? null),
                            fn (Builder $query) => $query->where(
                                'kode_permintaan',
                                $data['value']
                            )
                        );
                    })
                    // Tanpa penanda ini pengguna tidak melihat bahwa daftar
                    // sedang tersaring, dan tidak dapat menghapusnya sekali klik.
                    ->indicateUsing(fn (array $data): ?string => filled($data['value'] ?? null)
                        ? 'Kode: ' . $data['value']
                        : null),

                SelectFilter::make('status')
                    ->label('Status')
                    ->multiple()
                    // Hanya status yang mungkin muncul pada daftar aktif.
                    // Status akhir dipindahkan ke halaman Riwayat, sehingga
                    // menawarkannya di sini hanya akan menghasilkan daftar kosong.
                    ->options(collect(PermintaanBarang::STATUS)
                        ->except(PermintaanBarang::STATUS_RIWAYAT)
                        ->all()),

                // Dipakai pula sebagai sasaran tautan panel "Permintaan per
                // Tim Kerja" pada dashboard. Tidak ditampilkan bagi Tim dan
                // Ketua Tim, sebab daftar mereka telah dibatasi pada timnya.
                SelectFilter::make('tim_pemohon_id')
                    ->label('Tim Pemohon')
                    ->relationship('tim', 'nama_tim')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => in_array(
                        auth()->user()?->role,
                        ['kasubbag', 'petugas_gudang', 'admin']
                    )),

                SelectFilter::make('periode')
                    ->label('Periode')
                    ->options([
                        '1jam'   => '1 jam terakhir',
                        '1hari'  => '1 hari terakhir',
                        '7hari'  => '7 hari terakhir',
                        '30hari' => '1 bulan terakhir',
                    ])
                    ->placeholder('Semua waktu')
                    ->query(function (Builder $query, array $data): Builder {
                        $batas = match ($data['value'] ?? null) {
                            '1jam'   => now()->subHour(),
                            '1hari'  => now()->subDay(),
                            '7hari'  => now()->subDays(7),
                            '30hari' => now()->subDays(30),
                            default  => null,
                        };

                        return $batas
                            ? $query->where('created_at', '>=', $batas)
                            : $query;
                    }),
            ])
            // Seluruh interaksi bermuara pada satu pintu: klik baris membuka
            // pop-up Rincian (Instruksi §41). Tidak ada lagi tombol tahapan
            // yang berdiri sendiri di baris — Setujui, Verifikasi, Konfirmasi,
            // dan seterusnya kini menjadi tombol di kaki pop-up Rincian,
            // sehingga pengguna wajib melihat rincian permintaan sebelum
            // menjalankan aksi tahapannya. Lihat {@see static::aksiDetail()}.
            ->recordAction('detail')
            ->recordActions([
                static::aksiDetail(),
            ])
            ->recordActionsAlignment('right');
    }

    /**
     * Tautan menuju pop-up Rincian sebuah permintaan.
     *
     * Satu susunan yang dipakai bersama oleh widget "Perlu Tindakan", lonceng
     * notifikasi, dan pesan WhatsApp, supaya ketiganya bermuara di tempat yang
     * sama: Daftar Permintaan Barang yang tersaring pada permintaan itu, dengan
     * pop-up Rincian yang langsung terbuka lewat parameter `tableAction`
     * bawaan Filament. Tidak ada lagi tautan ke halaman rincian `/{id}`.
     *
     * Permintaan yang sudah menjadi riwayat tidak lagi tampil pada daftar
     * aktif, sehingga tautannya diarahkan ke halaman Riwayat yang memakai
     * pop-up Rincian yang sama.
     *
     * Panel disebut tegas agar tautan tetap benar ketika dibentuk di luar
     * permintaan HTTP — misalnya oleh job pengiriman WhatsApp.
     */
    public static function urlRincian(PermintaanBarang $permintaan): string
    {
        $riwayat = in_array($permintaan->status, PermintaanBarang::STATUS_RIWAYAT, true);

        if ($riwayat) {
            return Riwayat::getUrl(
                [
                    'jenis'             => 'permintaan',
                    'tableAction'       => 'detail',
                    'tableActionRecord' => $permintaan->getKey(),
                    'filters'           => ['kode_permintaan' => ['value' => $permintaan->kode_permintaan]],
                ],
                isAbsolute: true,
                panel: 'admin',
            );
        }

        return static::getUrl('index', [
            'tableAction'       => 'detail',
            'tableActionRecord' => $permintaan->getKey(),
            'filters'           => ['kode_permintaan' => ['value' => $permintaan->kode_permintaan]],
        ], isAbsolute: true, panel: 'admin');
    }

    /**
     * Tombol pengunduhan dokumen bukti permintaan.
     *
     * Didefinisikan satu kali dan dipakai baik pada kaki dialog rincian maupun
     * pada kepala halaman rincian, agar bentuknya seragam dengan tombol aksi
     * lain pada sistem: tombol bergaris dengan warna utama SIMPBI, bukan tautan
     * abu-abu yang tampak berbeda sendiri di antara tombol alur persetujuan.
     * Rupanya kini diambil dari GayaUnduh, acuan seluruh tombol pengunduhan.
     */
    public static function aksiUnduhBukti(): Action
    {
        return GayaUnduh::terapkan(Action::make('unduhBukti'))
            ->label('Unduh Bukti')
            ->tooltip('Unduh dokumen bukti permintaan yang telah disahkan')
            ->visible(fn ($record) => filled($record?->file_bukti_path))
            ->url(fn ($record) => route('bukti.unduh', $record))
            ->openUrlInNewTab();
    }

    // =====================================================================
    // AKSI TAHAPAN — DIJALANKAN DARI KAKI POP-UP RINCIAN
    // =====================================================================

    /** ---------- TAHAP 1 : PERSETUJUAN KETUA TIM ---------- */

    protected static function aksiSetujuiKetua(): Action
    {
        return Action::make('setujui')
            ->label('Setujui')
            ->icon('heroicon-m-check')
            ->color('success')
            ->button()
            ->visible(fn ($record) => auth()->user()->role === 'ketua_tim'
                && $record->status === 'menunggu_ketua')
            ->modalHeading('Setujui Permintaan')
            ->modalDescription('Permintaan akan diteruskan kepada Petugas Gudang untuk verifikasi ketersediaan fisik.')
            ->modalSubmitActionLabel('Setujui')
            ->modalCancelActionLabel('Batal')
            ->schema([
                Textarea::make('catatan')->label('Catatan (opsional)')->rows(2),
            ])
            ->action(fn (array $data, $record) => static::setujuiKetua($record, $data['catatan'] ?? null));
    }

    protected static function aksiTolakKetua(): Action
    {
        return Action::make('tolak')
            ->label('Tolak')
            ->icon('heroicon-m-x-mark')
            ->color('danger')
            ->button()
            ->outlined()
            ->visible(fn ($record) => auth()->user()->role === 'ketua_tim'
                && $record->status === 'menunggu_ketua')
            ->modalHeading('Tolak Permintaan')
            ->modalDescription('Stok yang dikunci akan dilepaskan kembali.')
            ->modalSubmitActionLabel('Tolak')
            ->modalCancelActionLabel('Batal')
            ->schema([
                Textarea::make('catatan')->label('Alasan Penolakan')->rows(2)->required(),
            ])
            ->action(fn (array $data, $record) => static::tolakKetua($record, $data['catatan']));
    }

    /** ---------- TAHAP 2 : VERIFIKASI KETERSEDIAAN FISIK ---------- */

    protected static function aksiVerifikasi(): Action
    {
        return Action::make('verifikasi')
            ->label('Verifikasi')
            ->icon('heroicon-m-clipboard-document-check')
            ->color('info')
            ->button()
            ->visible(fn ($record) => auth()->user()->role === 'petugas_gudang'
                && $record->status === 'menunggu_verifikasi')
            ->modalHeading('Verifikasi Ketersediaan Fisik')
            ->modalDescription('Isi jumlah hasil pengecekan fisik untuk setiap barang.')
            ->modalSubmitActionLabel('Simpan Hasil Verifikasi')
            ->modalCancelActionLabel('Batal')
            ->modalWidth('3xl')
            ->fillForm(fn ($record) => [
                'items' => $record->detail->map(fn ($d) => [
                    'detail_id'          => $d->id,
                    'nama'               => $d->barang?->nama_barang . ' (' . $d->barang?->satuan . ')',
                    'jumlah_diminta'     => $d->jumlah_diminta,
                    'jumlah_verif_fisik' => $d->jumlah_verif_fisik ?? $d->jumlah_diminta,
                    'kondisi_verif'      => $d->kondisi_verif ?? 'tersedia',
                ])->toArray(),
            ])
            ->schema([
                Repeater::make('items')
                    ->label('Rincian Barang')
                    ->schema([
                        Hidden::make('detail_id'),

                        Placeholder::make('nama')
                            ->label('Nama Barang'),

                        Placeholder::make('jumlah_diminta')
                            ->label('Diminta'),

                        TextInput::make('jumlah_verif_fisik')
                            ->label('Hasil Pengecekan')
                            ->numeric()
                            ->minValue(0)
                            ->required(),

                        Select::make('kondisi_verif')
                            ->label('Kondisi')
                            ->options([
                                'tersedia' => 'Tersedia',
                                'rusak'    => 'Rusak',
                                'kurang'   => 'Kurang',
                            ])
                            ->required(),
                    ])
                    ->columns(4)
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false),

                Textarea::make('keterangan')
                    ->label('Keterangan Verifikasi')
                    ->rows(2),
            ])
            ->action(fn (array $data, $record) => static::simpanVerifikasi($record, $data));
    }

    /** ---------- TAHAP 3 : PERSETUJUAN AKHIR KASUBBAG UMUM ---------- */

    protected static function aksiSetujuiKasubbag(): Action
    {
        return Action::make('setujuiKasubbag')
            ->label('Setujui')
            ->icon('heroicon-m-check-badge')
            ->color('success')
            ->button()
            ->visible(fn ($record) => auth()->user()->role === 'kasubbag'
                && $record->status === 'menunggu_kasubbag')
            ->modalHeading('Persetujuan Akhir Permintaan')
            ->modalDescription('Tetapkan jumlah yang disetujui untuk setiap barang. Jumlah dapat lebih kecil daripada jumlah yang diminta apabila permintaan disetujui sebagian.')
            ->modalSubmitActionLabel('Setujui')
            ->modalCancelActionLabel('Batal')
            ->modalWidth('3xl')
            ->fillForm(fn ($record) => [
                'items' => $record->detail->map(fn ($d) => [
                    'detail_id'      => $d->id,
                    'nama'           => $d->barang?->nama_barang . ' (' . $d->barang?->satuan . ')',
                    'jumlah_diminta' => $d->jumlah_diminta,
                    'hasil_cek'      => $d->jumlah_verif_fisik ?? $d->jumlah_diminta,
                    'jumlah_final'   => $d->jumlah_final ?? $d->jumlah_verif_fisik ?? $d->jumlah_diminta,
                ])->toArray(),
            ])
            ->schema([
                Repeater::make('items')
                    ->label('Rincian Barang')
                    ->schema([
                        Hidden::make('detail_id'),

                        Placeholder::make('nama')
                            ->label('Nama Barang'),

                        Placeholder::make('jumlah_diminta')
                            ->label('Diminta'),

                        Placeholder::make('hasil_cek')
                            ->label('Hasil Cek'),

                        // Batasnya dibaca dari rincian yang tersimpan, bukan dari isian
                        // formulir, sehingga muatan yang dimodifikasi tak dapat mengubahnya.
                        TextInput::make('jumlah_final')
                            ->label('Disetujui')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(fn ($record, \Filament\Schemas\Components\Utilities\Get $get): ?int => $record->detail
                                ->firstWhere('id', (int) $get('detail_id'))?->jumlah_diminta)
                            ->validationMessages(['max' => 'Jumlah disetujui tidak boleh melebihi jumlah diminta (:max).'])
                            ->required(),
                    ])
                    ->columns(4)
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false),

                Textarea::make('catatan')
                    ->label('Catatan (opsional)')
                    ->rows(2),
            ])
            ->action(fn (array $data, $record) => static::setujuiKasubbag($record, $data));
    }

    protected static function aksiTolakKasubbag(): Action
    {
        return Action::make('tolakKasubbag')
            ->label('Tolak')
            ->icon('heroicon-m-x-mark')
            ->color('danger')
            ->button()
            ->outlined()
            ->visible(fn ($record) => auth()->user()->role === 'kasubbag'
                && $record->status === 'menunggu_kasubbag')
            ->modalHeading('Tolak Permintaan')
            ->modalDescription('Stok yang dikunci akan dilepaskan kembali.')
            ->modalSubmitActionLabel('Tolak')
            ->modalCancelActionLabel('Batal')
            ->schema([
                Textarea::make('catatan')->label('Alasan Penolakan')->rows(2)->required(),
            ])
            ->action(fn (array $data, $record) => static::tolakKasubbag($record, $data['catatan']));
    }

    /** ---------- TAHAP 4 : PENYIAPAN BARANG ---------- */

    protected static function aksiSiapkan(): Action
    {
        return Action::make('siapkan')
            ->label('Barang Siap Diambil')
            ->icon('heroicon-m-archive-box')
            ->color('info')
            ->button()
            ->visible(fn ($record) => auth()->user()->role === 'petugas_gudang'
                && $record->status === 'siap_diproses')
            ->modalHeading('Penyiapan Barang')
            ->modalDescription('Tandai bahwa barang telah disiapkan dan dapat diambil oleh tim kerja pemohon.')
            ->modalSubmitActionLabel('Tandai Siap Diambil')
            ->modalCancelActionLabel('Batal')
            ->modalWidth(Width::Medium)
            ->schema([
                /*
                 * Tanda tangan tidak lagi digambar di sini. Petugas Gudang
                 * mendaftarkan tanda tangannya sekali saat melengkapi akun,
                 * lalu tanda tangan tersimpan itu yang dibubuhkan pada dokumen
                 * bukti. Yang tersisa di tahap ini hanyalah konfirmasi bahwa
                 * pelaksananya memang orang yang bersangkutan — dengan mengetik
                 * NIP-nya sendiri — supaya aksi tidak dapat terpicu tanpa sengaja.
                 */
                static::pratinjauTtd(fn () => auth()->user()),
                static::bidangKonfirmasiNip(),
            ])
            ->action(fn ($record) => static::tandaiSiapDiambil($record));
    }

    /** ---------- TAHAP 5 : KONFIRMASI PENERIMAAN ---------- */

    protected static function aksiKonfirmasi(): Action
    {
        return Action::make('konfirmasi')
            ->label('Konfirmasi Penerimaan')
            ->icon('heroicon-m-hand-thumb-up')
            ->color('success')
            ->button()
            ->visible(fn ($record) => in_array(auth()->user()->role, ['tim', 'ketua_tim'])
                && $record->status === 'siap_diambil')
            ->modalWidth(Width::Medium)
            ->modalHeading('Konfirmasi Penerimaan Barang')
            ->modalDescription('Konfirmasi penerimaan akan mengurangi stok fisik barang dan mencatat transaksi pada kartu kendali persediaan.')
            ->modalSubmitActionLabel('Konfirmasi')
            ->modalCancelActionLabel('Batal')
            ->schema([
                Select::make('sesuai')
                    ->label('Apakah barang yang diterima sesuai?')
                    ->options([
                        'ya'    => 'Ya, barang sesuai',
                        'tidak' => 'Tidak, terdapat ketidaksesuaian',
                    ])
                    ->default('ya')
                    ->live()
                    ->required(),

                Textarea::make('deskripsi')
                    ->label('Deskripsi Ketidaksesuaian')
                    ->rows(2)
                    ->required()
                    ->visible(fn ($get) => $get('sesuai') === 'tidak'),

                Select::make('dapat_diatasi')
                    ->label('Tindak Lanjut')
                    ->options([
                        '1' => 'Dapat diatasi di tempat, barang ditukar atau dilengkapi',
                        '0' => 'Tidak dapat diatasi',
                    ])
                    ->required()
                    ->visible(fn ($get) => $get('sesuai') === 'tidak'),

                /*
                 * Dokumen bukti selalu terbit atas nama Ketua Tim, meski
                 * penerimaan boleh dikonfirmasi anggota timnya. Yang dibubuhkan
                 * adalah tanda tangan Ketua Tim yang sudah tersimpan — bukan
                 * gambar baru, dan bukan tanda tangan si pengklik. Pratinjaunya
                 * hanya tampil ketika barang benar-benar diterima, sebab
                 * penerimaan yang berakhir bermasalah tidak menerbitkan dokumen.
                 */
                static::pratinjauTtd(fn ($record) => static::penandaTanganPenerima($record))
                    ->visible(fn ($get): bool => static::penerimaanMenerbitkanDokumen($get)),

                /*
                 * Anggota tim yang mengkonfirmasi perlu tahu sejak awal bila
                 * dokumen tidak akan dapat terbit — daripada mengisi seluruh
                 * formulir lalu ditolak pada saat menekan tombol.
                 */
                Placeholder::make('ketuaBelumBertandaTangan')
                    ->hiddenLabel()
                    ->visible(fn ($record, $get): bool => static::penerimaanMenerbitkanDokumen($get)
                        && auth()->user()->role !== 'ketua_tim'
                        && ! TandaTangan::tersedia(static::ketuaTimPemohon($record)))
                    ->content(fn ($record) => new HtmlString(
                        '<p class="text-sm text-danger-600 dark:text-danger-400">'
                        . 'Dokumen bukti permintaan terbit atas nama Ketua Tim, sedangkan '
                        . e(static::ketuaTimPemohon($record)?->name ?? 'Ketua Tim ' . ($record->tim?->nama_tim ?? ''))
                        . ' belum menyimpan tanda tangan. Mintalah beliau menyelesaikan pelengkapan akun, '
                        . 'atau biarkan beliau sendiri yang mengkonfirmasi penerimaan ini.'
                        . '</p>'
                    )),

                static::bidangKonfirmasiNip(),
            ])
            ->action(fn (array $data, $record) => static::konfirmasiPenerimaan($record, $data));
    }

    /** ---------- TAHAP 6 : PENGESAHAN AKHIR ---------- */

    protected static function aksiSahkan(): Action
    {
        return Action::make('sahkan')
            ->label('Sahkan')
            ->icon('heroicon-m-check-badge')
            ->color('success')
            ->button()
            ->visible(fn ($record) => auth()->user()->role === 'kasubbag'
                && $record->status === 'menunggu_pengesahan')
            ->modalHeading('Pengesahan Akhir Permintaan')
            ->modalDescription('Pengesahan akan menerbitkan dokumen bukti permintaan beserta kode QR verifikasi.')
            ->modalSubmitActionLabel('Sahkan')
            ->modalCancelActionLabel('Batal')
            // Tidak ada pratinjau tanda tangan tersimpan di sini: pengesahan
            // Kasubbag membubuhkan e-TTD (nama + kode QR), bukan gambar tanda
            // tangan. Yang tersisa hanyalah konfirmasi identitas — mengetik NIP
            // — agar pengesahan tidak terpicu tanpa sengaja.
            ->schema([
                Textarea::make('catatan')->label('Catatan (opsional)')->rows(2),
                static::bidangKonfirmasiNip(),
            ])
            ->action(fn (array $data, $record) => static::sahkan($record, $data['catatan'] ?? null));
    }

    // =====================================================================
    // KONFIRMASI IDENTITAS PENGGANTI TANDA TANGAN BASAH
    // =====================================================================

    /**
     * Kolom konfirmasi identitas pelaksana.
     *
     * Menggantikan gambar tanda tangan pada tahapan: pengguna cukup mengetik
     * NIP-nya sendiri (atau nama lengkap bila NIP belum terdata) sebagai
     * pernyataan sadar bahwa ia menjalankan aksi ini. Pemeriksaannya dilakukan
     * di sisi peladen terhadap data akun yang sedang masuk, bukan terhadap
     * nilai yang dikirim dari peramban, sehingga tidak dapat dipalsukan dengan
     * menyunting formulir.
     *
     * Akun peran Tim adalah akun bersama satu tim dan tidak punya NIP, sehingga
     * yang diketiknya adalah nama timnya. Nilai yang diketik hanya diperiksa,
     * tidak disimpan atau dicetak; tanda tangan dan nama pada dokumen tetap
     * milik Ketua Tim.
     */
    protected static function bidangKonfirmasiNip(): TextInput
    {
        $pengguna     = auth()->user();
        $pakaiNamaTim = $pengguna?->role === 'tim';
        $pakaiNip     = filled($pengguna?->nip);

        return TextInput::make('konfirmasi_nip')
            ->label($pakaiNamaTim
                ? 'Ketik nama tim Anda untuk konfirmasi'
                : ($pakaiNip
                    ? 'Ketik NIP Anda untuk mengonfirmasi'
                    : 'Ketik nama lengkap Anda untuk mengonfirmasi'))
            ->placeholder($pakaiNamaTim
                ? $pengguna->tim?->nama_tim
                : ($pakaiNip ? $pengguna->nip : $pengguna?->name))
            ->required()
            ->autocomplete('off')
            ->helperText($pakaiNamaTim
                ? 'Konfirmasi ini menggantikan tanda tangan basah. Tanda tangan Ketua Tim yang tersimpan dibubuhkan otomatis.'
                : 'Konfirmasi ini menggantikan tanda tangan basah. Tanda tangan Anda yang tersimpan dibubuhkan otomatis.')
            // Dibungkus closure tak berparameter: Filament mengevaluasi
            // pembungkusnya untuk memperoleh aturan, lalu Laravel yang memanggil
            // closure aturan di dalamnya dengan ($attribute, $value, $fail).
            ->rule(static fn (): Closure => static function (string $attribute, $value, Closure $fail): void {
                $pengguna = auth()->user();

                if ($pengguna?->role === 'tim') {
                    if (blank($pengguna->tim?->nama_tim)) {
                        $fail('Akun Anda belum terhubung ke tim kerja, sehingga konfirmasi tidak dapat dilakukan. Hubungi Administrator.');

                        return;
                    }

                    if (! static::cocokIdentitas((string) $value)) {
                        $fail('Tidak cocok dengan nama tim Anda. Ketik persis nama tim Anda.');
                    }

                    return;
                }

                if (! static::cocokIdentitas((string) $value)) {
                    $fail('Tidak cocok dengan data akun Anda. Ketik persis NIP (atau nama lengkap) Anda.');
                }
            });
    }

    /** Apakah nilai yang diketik cocok dengan identitas pengguna yang masuk. */
    protected static function cocokIdentitas(string $nilai): bool
    {
        $pengguna = auth()->user();

        if (! $pengguna) {
            return false;
        }

        // Akun peran Tim: nama tim, tak peka huruf besar-kecil, spasi di ujung
        // dan spasi ganda diabaikan.
        if ($pengguna->role === 'tim') {
            $namaTim = static::ratakanTeks((string) $pengguna->tim?->nama_tim);

            return $namaTim !== '' && static::ratakanTeks($nilai) === $namaTim;
        }

        $harapan = filled($pengguna->nip) ? $pengguna->nip : $pengguna->name;

        return trim($nilai) !== '' && trim($nilai) === trim((string) $harapan);
    }

    /** Huruf kecil, spasi di ujung dibuang, deret spasi menjadi satu. */
    protected static function ratakanTeks(string $teks): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $teks)));
    }

    /**
     * Pratinjau tanda tangan tersimpan milik penanda tangan sebuah tahapan.
     *
     * Penanda tangannya bisa jadi bukan pengguna yang menekan tombol — pada
     * konfirmasi penerimaan, misalnya, yang tampil adalah tanda tangan Ketua
     * Tim meski yang mengklik anggota timnya. Karena itu penanda tangan
     * diserahkan lewat closure yang menerima record.
     */
    protected static function pratinjauTtd(Closure $penandaTangan): Placeholder
    {
        return Placeholder::make('pratinjau_ttd')
            ->hiddenLabel()
            ->content(function ($record) use ($penandaTangan) {
                /** @var User|null $orang */
                $orang = $penandaTangan($record);
                $uri   = TandaTangan::dataUri($orang);

                if (! $uri) {
                    return new HtmlString(
                        '<p class="text-sm text-danger-600 dark:text-danger-400">'
                        . 'Belum ada tanda tangan tersimpan untuk dibubuhkan.'
                        . '</p>'
                    );
                }

                return new HtmlString(
                    '<div class="text-sm text-gray-600 dark:text-gray-300">'
                    . 'Tanda tangan tersimpan atas nama <span class="font-medium">' . e($orang->name) . '</span> '
                    . 'akan dibubuhkan pada dokumen:'
                    . '</div>'
                    . '<img src="' . $uri . '" alt="Tanda tangan tersimpan" '
                    . 'class="mt-2 h-16 rounded border border-gray-200 bg-white p-1 dark:border-gray-700">'
                );
            })
            ->columnSpanFull();
    }

    /** Apakah keadaan konfirmasi saat ini akan menerbitkan dokumen bukti. */
    protected static function penerimaanMenerbitkanDokumen(\Filament\Schemas\Components\Utilities\Get $get): bool
    {
        if ($get('sesuai') === 'ya') {
            return true;
        }

        return $get('sesuai') === 'tidak' && $get('dapat_diatasi') === '1';
    }

    /**
     * Ketua Tim yang tanda tangannya dibubuhkan pada dokumen penerimaan:
     * dirinya sendiri bila Ketua Tim yang mengklik, atau Ketua Tim tim pemohon
     * bila anggota timnya yang mengklik.
     */
    protected static function penandaTanganPenerima(?PermintaanBarang $record): ?User
    {
        return auth()->user()?->role === 'ketua_tim'
            ? auth()->user()
            : static::ketuaTimPemohon($record);
    }

    // =====================================================================
    // TINDAKAN PADA ALUR PERSETUJUAN
    // =====================================================================

    /**
     * Penjaga sisi peladen: memastikan permintaan masih berada pada status yang
     * diharapkan tahapan ini. Melindungi dari aksi yang terpicu dari pop-up yang
     * dibuka sebelum orang lain lebih dulu memproses permintaan yang sama, atau
     * dari tautan lama yang menuju keadaan yang sudah berlalu.
     */
    protected static function pastikanStatus(PermintaanBarang $record, string|array $status): bool
    {
        if (in_array($record->status, (array) $status, true)) {
            return true;
        }

        Notification::make()
            ->title('Permintaan sudah berubah')
            ->body('Tahap ini sudah ditangani atau statusnya berubah sejak pop-up dibuka. Muat ulang halaman untuk melihat keadaan terbaru.')
            ->warning()
            ->send();

        return false;
    }

    /** Menyetujui pada tahap Ketua Tim (BPMN P.2.9). */
    protected static function setujuiKetua(PermintaanBarang $record, ?string $catatan): void
    {
        if (! static::pastikanStatus($record, 'menunggu_ketua')) {
            return;
        }

        DB::transaction(function () use ($record, $catatan) {
            $record->update([
                'status'          => 'menunggu_verifikasi',
                'hold_expired_at' => JamKerja::batas(static::batasJam('batas_verifikasi_jam')),
            ]);

            static::catatRiwayat($record, 'ketua_tim', 'setuju', $catatan);
        });

        // Memberitahukan pihak yang harus bertindak berikutnya
        app(NotifikasiService::class)->permintaanBerubah($record->refresh(), $catatan);

        Notification::make()
            ->title('Permintaan disetujui')
            ->body('Diteruskan kepada Petugas Gudang untuk verifikasi ketersediaan fisik.')
            ->success()
            ->send();
    }

    /** Menolak pada tahap Ketua Tim, disertai pelepasan kunci stok. */
    protected static function tolakKetua(PermintaanBarang $record, string $catatan): void
    {
        if (! static::pastikanStatus($record, 'menunggu_ketua')) {
            return;
        }

        DB::transaction(function () use ($record, $catatan) {
            app(StokService::class)->release($record);

            $record->update([
                'status'          => 'ditolak_ketua',
                'hold_expired_at' => null,
            ]);

            static::catatRiwayat($record, 'ketua_tim', 'tolak', $catatan);
        });

        // Memberitahukan pihak yang harus bertindak berikutnya
        app(NotifikasiService::class)->permintaanBerubah($record->refresh(), $catatan);

        Notification::make()
            ->title('Permintaan ditolak')
            ->body('Stok yang dikunci telah dilepaskan kembali.')
            ->warning()
            ->send();
    }

    /**
     * Menyimpan hasil verifikasi ketersediaan fisik (BPMN P.2.S6).
     * Hasil verifikasi diteruskan kepada Kasubbag Umum untuk persetujuan akhir.
     */
    protected static function simpanVerifikasi(PermintaanBarang $record, array $data): void
    {
        if (! static::pastikanStatus($record, 'menunggu_verifikasi')) {
            return;
        }

        DB::transaction(function () use ($record, $data) {
            foreach ($data['items'] as $item) {
                $record->detail()
                    ->where('id', $item['detail_id'])
                    ->update([
                        'jumlah_verif_fisik' => (int) $item['jumlah_verif_fisik'],
                        'kondisi_verif'      => $item['kondisi_verif'],
                    ]);
            }

            $record->update([
                'status'          => 'menunggu_kasubbag',
                'hold_expired_at' => JamKerja::batas(static::batasJam('batas_kasubbag_jam')),
            ]);

            static::catatRiwayat($record, 'verifikasi', 'selesai', $data['keterangan'] ?? null);
        });

        // Memberitahukan pihak yang harus bertindak berikutnya
        app(NotifikasiService::class)->permintaanBerubah($record->refresh(), null);

        Notification::make()
            ->title('Hasil verifikasi tersimpan')
            ->body('Permintaan diteruskan kepada Kasubbag Umum untuk persetujuan akhir.')
            ->success()
            ->send();
    }

    /**
     * Persetujuan akhir oleh Kasubbag Umum (BPMN P.2.S7).
     * Apabila jumlah yang disetujui lebih kecil daripada jumlah yang diminta,
     * kelebihan kunci stok dilepaskan melalui StokService::sesuaikanHold().
     */
    protected static function setujuiKasubbag(PermintaanBarang $record, array $data): void
    {
        if (! static::pastikanStatus($record, 'menunggu_kasubbag')) {
            return;
        }

        // Penjaga kedua di sisi peladen, terhadap jumlah diminta yang tersimpan.
        try {
            app(StokService::class)->pastikanTidakMelebihiDiminta(
                $record,
                collect($data['items'])->pluck('jumlah_final', 'detail_id')->all(),
            );
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title('Persetujuan tidak dapat disimpan')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        DB::transaction(function () use ($record, $data) {
            foreach ($data['items'] as $item) {
                $record->detail()
                    ->where('id', $item['detail_id'])
                    ->update(['jumlah_final' => (int) $item['jumlah_final']]);
            }

            $record->refresh();
            app(StokService::class)->sesuaikanHold($record);

            $record->update([
                'status'          => 'siap_diproses',
                'hold_expired_at' => JamKerja::batas(static::batasJam('batas_penyiapan_jam')),
            ]);

            static::catatRiwayat($record, 'kasubbag', 'setuju', $data['catatan'] ?? null);
        });

        // Memberitahukan pihak yang harus bertindak berikutnya
        app(NotifikasiService::class)->permintaanBerubah($record->refresh(), null);

        Notification::make()
            ->title('Permintaan disetujui')
            ->body('Diteruskan kepada Petugas Gudang untuk penyiapan barang.')
            ->success()
            ->send();
    }

    /** Menolak pada tahap Kasubbag Umum, disertai pelepasan kunci stok. */
    protected static function tolakKasubbag(PermintaanBarang $record, string $catatan): void
    {
        if (! static::pastikanStatus($record, 'menunggu_kasubbag')) {
            return;
        }

        DB::transaction(function () use ($record, $catatan) {
            app(StokService::class)->release($record);

            $record->update([
                'status'          => 'ditolak_kasubbag',
                'hold_expired_at' => null,
            ]);

            static::catatRiwayat($record, 'kasubbag', 'tolak', $catatan);
        });

        // Memberitahukan pihak yang harus bertindak berikutnya
        app(NotifikasiService::class)->permintaanBerubah($record->refresh(), $catatan);

        Notification::make()
            ->title('Permintaan ditolak')
            ->body('Stok yang dikunci telah dilepaskan kembali.')
            ->warning()
            ->send();
    }

    /**
     * Ketua Tim dari tim kerja pemohon sebuah permintaan.
     *
     * Dokumen bukti selalu terbit atas nama Ketua Tim, sehingga tanda tangan
     * penerima diambil dari orang ini — bukan dari siapa pun yang kebetulan
     * menekan tombol konfirmasi.
     */
    protected static function ketuaTimPemohon(?PermintaanBarang $record): ?User
    {
        return $record?->tim?->ketuaTim;
    }

    /**
     * Memastikan penanda tangan sebuah tahapan memang punya tanda tangan
     * tersimpan yang dapat dibubuhkan.
     *
     * Tidak ada lagi goresan yang disimpan di sini — tanda tangan didaftarkan
     * sekali saat pengguna melengkapi akun. Yang tersisa adalah lapis
     * pertahanan terakhir: bila entah bagaimana penanda tangannya belum punya
     * tanda tangan, tahapan dihentikan dengan keterangan, bukan diteruskan
     * dengan kotak tanda tangan kosong pada dokumen.
     *
     * Mengembalikan false bila tahapan tidak boleh dilanjutkan.
     */
    protected static function pastikanTandaTangan(
        ?User $penandaTangan,
        ?PermintaanBarang $record = null,
    ): bool {
        if (TandaTangan::tersedia($penandaTangan)) {
            return true;
        }

        Notification::make()
            ->title('Tanda tangan belum tersedia')
            ->body($penandaTangan
                ? $penandaTangan->name . ' belum menyimpan tanda tangan, sehingga dokumen bukti '
                    . 'tidak akan dapat diterbitkan. Mintalah beliau melengkapinya melalui pelengkapan akun.'
                : 'Tim Kerja ' . ($record?->tim?->nama_tim ?? 'pemohon') . ' belum memiliki Ketua Tim, '
                    . 'sehingga tidak ada yang dapat menandatangani penerimaan barang.')
            ->danger()
            ->persistent()
            ->send();

        return false;
    }

    /** Menandai barang telah disiapkan dan siap diambil (BPMN P.2.S8). */
    protected static function tandaiSiapDiambil(PermintaanBarang $record): void
    {
        if (! static::pastikanStatus($record, 'siap_diproses')) {
            return;
        }

        if (! static::pastikanTandaTangan(auth()->user())) {
            return;
        }

        DB::transaction(function () use ($record) {
            $record->update([
                'status'          => 'siap_diambil',
                'hold_expired_at' => JamKerja::batas(static::batasJam('batas_pengambilan_jam')),
            ]);

            static::catatRiwayat($record, 'penyiapan', 'selesai', 'Barang telah disiapkan.');
        });

        // Memberitahukan pihak yang harus bertindak berikutnya
        app(NotifikasiService::class)->permintaanBerubah($record->refresh(), null);

        Notification::make()
            ->title('Barang ditandai siap diambil')
            ->body('Tim kerja pemohon dapat mengambil barang di gudang.')
            ->success()
            ->send();
    }

    /**
     * Konfirmasi penerimaan barang oleh pemohon (BPMN P.2.S9 dan P.2.S9a).
     *
     * Apabila barang sesuai, kunci stok dikonversi menjadi pengeluaran dan
     * dicatat pada tabel mutasi_stok sebagai dasar kartu kendali.
     * Apabila terdapat ketidaksesuaian yang tidak dapat diatasi, kunci stok
     * dilepaskan dan permintaan ditandai bermasalah.
     */
    protected static function konfirmasiPenerimaan(PermintaanBarang $record, array $data): void
    {
        if (! static::pastikanStatus($record, 'siap_diambil')) {
            return;
        }

        $sesuai       = ($data['sesuai'] ?? 'ya') === 'ya';
        $dapatDiatasi = ($data['dapat_diatasi'] ?? '1') === '1';

        /*
         * Tanda tangan hanya dituntut ketika barang benar-benar diterima.
         * Penerimaan yang berakhir bermasalah tidak menerbitkan dokumen bukti,
         * sehingga menahan alurnya karena tanda tangan justru mengurung
         * permintaan yang memang perlu segera dihentikan.
         */
        if ($sesuai || $dapatDiatasi) {
            if (! static::pastikanTandaTangan(static::penandaTanganPenerima($record), $record)) {
                return;
            }
        }

        DB::transaction(function () use ($record, $data, $sesuai, $dapatDiatasi) {

            // Catat temuan ketidaksesuaian bila ada
            if (! $sesuai) {
                $record->ketidaksesuaian()->create([
                    'petugas_id'    => auth()->id(),
                    'deskripsi'     => $data['deskripsi'],
                    'dapat_diatasi' => $dapatDiatasi,
                    'tindak_lanjut' => $dapatDiatasi
                        ? 'Barang ditukar atau dilengkapi di tempat.'
                        : 'Permintaan dihentikan, stok dilepaskan kembali.',
                    'status'        => $dapatDiatasi ? 'selesai_di_tempat' : 'permanen',
                ]);
            }

            // Ketidaksesuaian yang tidak dapat diatasi menghentikan permintaan
            if (! $sesuai && ! $dapatDiatasi) {
                app(StokService::class)->release($record);

                $record->update([
                    'status'          => 'bermasalah',
                    'hold_expired_at' => null,
                ]);

                static::catatRiwayat($record, 'konfirmasi', 'tolak', $data['deskripsi']);

                return;
            }

            // Barang diterima: kunci stok dikonversi menjadi pengeluaran
            app(StokService::class)->konversi($record, auth()->id());

            $record->update([
                'status'           => 'menunggu_pengesahan',
                'hold_expired_at'  => null,
                'hold_released_at' => now(),
            ]);

            static::catatRiwayat($record, 'konfirmasi', 'selesai', 'Barang diterima oleh pemohon.');
        });

        // Memberitahukan pihak yang harus bertindak berikutnya, sekaligus
        // memperingatkan pengelola bila pengeluaran barang membuat stoknya
        // menipis atau habis.
        app(NotifikasiService::class)->permintaanBerubah($record->refresh(), $data['deskripsi'] ?? null);

        if ($sesuai || $dapatDiatasi) {
            foreach ($record->detail as $rincian) {
                if ($rincian->barang) {
                    app(NotifikasiService::class)->stokMenipis($rincian->barang->refresh());
                }
            }
        }

        if (! $sesuai && ! $dapatDiatasi) {
            Notification::make()
                ->title('Permintaan ditandai bermasalah')
                ->body('Stok yang dikunci telah dilepaskan kembali.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('Penerimaan barang dikonfirmasi')
            ->body('Stok fisik telah diperbarui. Permintaan menunggu pengesahan akhir Kasubbag Umum.')
            ->success()
            ->send();
    }

    /**
     * Pengesahan akhir oleh Kasubbag Umum (UC-15).
     *
     * Pengesahan menandai transaksi telah selesai dan sah secara
     * administratif, sekaligus menerbitkan dokumen bukti permintaan
     * dalam bentuk PDF yang dilengkapi kode QR verifikasi.
     */
    protected static function sahkan(PermintaanBarang $record, ?string $catatan): void
    {
        if (! static::pastikanStatus($record, 'menunggu_pengesahan')) {
            return;
        }

        DB::transaction(function () use ($record, $catatan) {
            $record->update([
                'status'             => 'selesai',
                'pengesahan_oleh_id' => auth()->id(),
                'pengesahan_at'      => now(),
            ]);

            static::catatRiwayat($record, 'pengesahan', 'selesai', $catatan);

            // Dokumen dibentuk setelah riwayat tercatat, agar nama pengesah
            // dapat dibaca pada berkas yang dihasilkan.
            $record->refresh()->load('persetujuan.pelaksana');

            $path = app(DokumenPermintaanService::class)->buat($record);

            $record->update(['file_bukti_path' => $path]);
        });

        // Memberitahukan pihak yang harus bertindak berikutnya
        app(NotifikasiService::class)->permintaanBerubah($record->refresh(), $catatan);

        Notification::make()
            ->title('Permintaan disahkan')
            ->body('Dokumen bukti permintaan telah diterbitkan dan dapat diunduh.')
            ->success()
            ->send();
    }

    // =====================================================================
    // PEMBANTU
    // =====================================================================

    /**
     * Aksi pembuka pop-up rincian permintaan — sekaligus gerbang tunggal
     * menuju aksi tahapan.
     *
     * Dipakai bersama oleh daftar Permintaan Barang dan halaman Riwayat lewat
     * ->recordAction('detail'), supaya keduanya membuka rincian dengan cara
     * yang sama dan isinya tidak perlu ditulis dua kali.
     *
     * Tombol tahapan (Setujui, Verifikasi, Konfirmasi, dan seterusnya) diletakkan
     * di kaki pop-up ini sebagai extraModalFooterActions, bukan sebagai tombol
     * baris tersendiri. Dengan begitu pengguna wajib melihat rincian sebelum
     * menjalankan aksinya, dan hanya aksi yang sesuai peran serta status yang
     * ditampilkan — sisanya disembunyikan oleh closure ->visible() masing-masing.
     *
     * Label tombol barisnya mengikuti pekerjaan yang sedang menanti pengguna,
     * sehingga baris yang butuh tindakan langsung terbaca; baris tanpa tindakan
     * cukup berbunyi "Rincian".
     */
    public static function aksiDetail(): Action
    {
        return Action::make('detail')
            ->label(fn (PermintaanBarang $record): string => static::labelBaris($record))
            ->icon(fn (PermintaanBarang $record): string => TindakanPermintaan::ada(auth()->user(), $record)
                ? 'heroicon-m-arrow-right-circle'
                : 'heroicon-m-eye')
            ->button()
            ->color(fn (PermintaanBarang $record): string => TindakanPermintaan::ada(auth()->user(), $record)
                ? 'primary'
                : 'gray')
            ->tooltip('Lihat rincian permintaan')
            ->modalHeading(fn (PermintaanBarang $record) => 'Permintaan ' . $record->kode_permintaan)
            // Lebar sedang, bukan layar penuh: rincian ini dibaca sekilas
            // dan dialog selebar layar justru menyulitkan kembali ke daftar.
            ->modalWidth(Width::TwoExtraLarge)
            // Rincian hanya dibaca, sehingga dialog tidak punya tombol kirim
            // sendiri. Tombol tahapan di kaki dialoglah yang menjalankan aksi.
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            /*
             * Kaki dialog memuat: aksi tahapan yang sesuai peran + status, lalu
             * tombol unduh bukti. Berkas bukti baru terbit setelah permintaan
             * disahkan — dan permintaan yang sudah selesai berpindah ke halaman
             * Riwayat — sehingga tombol unduh di sini menjangkau kedua halaman
             * yang memakai dialog ini sekaligus.
             */
            ->extraModalFooterActions([
                static::aksiSetujuiKetua(),
                static::aksiTolakKetua(),
                static::aksiVerifikasi(),
                static::aksiSetujuiKasubbag(),
                static::aksiTolakKasubbag(),
                static::aksiSiapkan(),
                static::aksiKonfirmasi(),
                static::aksiSahkan(),
                static::aksiUnduhBukti(),
            ])
            // Rincian disajikan sebagai komponen skema, bukan modalContent.
            // Dengan begitu aksi ini memiliki skema yang dapat di-resolve, yang
            // dibutuhkan ketika aksi tahapan bersarang di kakinya memuat medan
            // ->live() (misalnya pilihan "sesuai" pada Konfirmasi Penerimaan).
            //
            // Relasi dimuat di sini, bukan pada kueri tabel, agar daftar tidak
            // menanggung kueri rincian untuk baris yang tidak dibuka.
            ->schema(fn (PermintaanBarang $record) => [
                View::make('filament.partials.detail-permintaan')
                    ->viewData(['record' => $record->load([
                        'tim',
                        'detail.barang',
                        'ketidaksesuaian',
                        'persetujuan.pelaksana',
                    ])]),
            ]);
    }

    /** Label tombol baris menurut pekerjaan yang sedang menanti pengguna. */
    protected static function labelBaris(PermintaanBarang $record): string
    {
        if (! TindakanPermintaan::ada(auth()->user(), $record)) {
            return 'Rincian';
        }

        return match ($record->status) {
            'menunggu_ketua'      => 'Tinjau',
            'menunggu_verifikasi' => 'Verifikasi',
            'menunggu_kasubbag'   => 'Tinjau',
            'siap_diproses'       => 'Siapkan',
            'siap_diambil'        => 'Konfirmasi',
            'menunggu_pengesahan' => 'Sahkan',
            default               => 'Rincian',
        };
    }

    /**
     * Mengambil batas waktu tahapan dari tabel pengaturan.
     *
     * Nilai cadangan delapan jam kerja setara satu hari kerja penuh, sama
     * dengan nilai yang ditanam migrasi, supaya baris pengaturan yang hilang
     * tidak diam-diam memperpanjang batas menjadi tiga hari kerja.
     */
    protected static function batasJam(string $kunci, int $bawaan = 8): int
    {
        return (int) (DB::table('pengaturan')->where('kunci', $kunci)->value('nilai') ?? $bawaan);
    }

    /** Mencatat satu baris pada riwayat persetujuan. */
    protected static function catatRiwayat(
        PermintaanBarang $record,
        string $tahap,
        string $keputusan,
        ?string $catatan = null
    ): void {
        RiwayatPersetujuan::create([
            'permintaan_id' => $record->id,
            'tahap'         => $tahap,
            'pelaksana_id'  => auth()->id(),
            'keputusan'     => $keputusan,
            'catatan'       => $catatan,
            'waktu'         => now(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPermintaanBarangs::route('/'),
            'detail' => Pages\DetailPermintaanBarang::route('/{record}'),
        ];
    }
}
