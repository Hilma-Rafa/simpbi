<?php

namespace App\Filament\Pages;

use App\Filament\Resources\BastMutasiAsets\BastMutasiAsetResource;
use App\Filament\Resources\PermintaanBarangs\PermintaanBarangResource;
use App\Jobs\KirimPesanWhatsApp;
use App\Models\MutasiStok;
use App\Models\Notifikasi;
use App\Models\PermintaanBarang;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
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
use Illuminate\Support\Str;
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
            /*
             * Riwayat pengiriman notifikasi hanya untuk Admin Sistem.
             * Isinya bukan informasi operasional melainkan catatan teknis
             * pengiriman — kanal, status, dan alasan kegagalan — yang
             * penanganannya berupa pemeriksaan gerbang WhatsApp dan pengiriman
             * ulang, dan itu kewenangan Admin. Peran lain cukup menerima
             * notifikasinya lewat lonceng.
             */
            'notifikasi' => [
                'label'   => 'Riwayat Pengiriman Notifikasi',
                'ikon'    => 'heroicon-m-bell-alert',
                'ringkas' => 'Notifikasi',
                'peran'   => ['admin'],
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

        /*
         * Tabelnya dibangun ulang di sini, bukan dibiarkan terbangun sendiri.
         *
         * Filament menyusun tabel pada tahap `booted`, yang berjalan sebelum
         * aksi Livewire ini dipanggil. Tanpa penyusunan ulang, tabel yang
         * dirender setelah penggantian jenis masih tabel jenis sebelumnya —
         * yang tampak bagi pengguna sebagai tab yang baru berpindah pada
         * klik kedua. Penyaringnya ikut disusun ulang sebab setiap jenis
         * memiliki penyaring yang berbeda.
         */
        $this->table = $this->table($this->makeTable());
        $this->cacheSchema('tableFiltersForm', $this->getTableFiltersForm(...));

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
            'notifikasi'  => $this->tabelNotifikasi($table),
            default       => $this->tabelPermintaan($table),
        };
    }

    /**
     * Riwayat pengiriman notifikasi seluruh kanal.
     *
     * Tanpa tampilan ini, kegagalan pengiriman WhatsApp hanya tersimpan di
     * basis data dan tidak diketahui siapa pun: notifikasinya tidak sampai,
     * sementara sistem tampak baik-baik saja. Kolom status dan alasan kegagalan
     * ditampilkan berdampingan agar penyebabnya — sesi gerbang terputus, nomor
     * belum diisi, kunci API ditolak — langsung terbaca dan dapat ditindaklanjuti.
     */
    protected function tabelNotifikasi(Table $table): Table
    {
        return $table
            ->query(fn () => Notifikasi::query()->with('user'))
            ->deferFilters(false)
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d-m-Y H:i')->sortable(),

                TextColumn::make('user.name')
                    ->label('Penerima')
                    ->searchable()
                    ->description(fn ($record) => $record->user?->no_hp ?: 'tanpa nomor'),

                TextColumn::make('judul')
                    ->label('Notifikasi')
                    ->searchable()
                    ->wrap()
                    ->description(fn ($record) => Str::limit($record->pesan, 80)),

                TextColumn::make('channel')
                    ->label('Kanal')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'whatsapp' ? 'WhatsApp' : 'Dalam Aplikasi')
                    ->color(fn (string $state) => $state === 'whatsapp' ? 'success' : 'gray'),

                TextColumn::make('status_kirim')
                    ->label('Status Kirim')
                    ->badge()
                    // Baris dalam aplikasi tidak melalui gerbang mana pun,
                    // sehingga status kirimnya memang kosong dan bukan kegagalan.
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'terkirim' => 'Terkirim',
                        'gagal'    => 'Gagal',
                        'pending'  => 'Menunggu',
                        default    => '—',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'terkirim' => 'success',
                        'gagal'    => 'danger',
                        'pending'  => 'warning',
                        default    => 'gray',
                    })
                    ->description(fn ($record) => $record->status_kirim === 'gagal'
                        ? Str::limit($record->error_message, 90)
                        : null),

                TextColumn::make('dikirim_at')
                    ->label('Dikirim')
                    ->dateTime('d-m-Y H:i')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('dibaca_at')
                    ->label('Dibaca')
                    ->dateTime('d-m-Y H:i')
                    ->placeholder('Belum')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('channel')
                    ->label('Kanal')
                    ->options(['in_app' => 'Dalam Aplikasi', 'whatsapp' => 'WhatsApp']),

                SelectFilter::make('status_kirim')
                    ->label('Status Kirim')
                    ->options(['pending' => 'Menunggu', 'terkirim' => 'Terkirim', 'gagal' => 'Gagal']),

                SelectFilter::make('tipe')
                    ->label('Jenis Kejadian')
                    ->options(['permintaan' => 'Permintaan', 'mutasi' => 'Mutasi Aset', 'stok' => 'Stok']),

                $this->penyaringPeriode('created_at', 'Waktu Terbit'),
            ])
            ->recordActions([
                Action::make('kirimUlang')
                    ->label('Kirim Ulang')
                    ->icon('heroicon-m-arrow-path')
                    ->color('warning')
                    ->outlined()
                    ->requiresConfirmation()
                    ->modalHeading('Kirim ulang notifikasi WhatsApp')
                    ->modalDescription('Pesan akan dikembalikan ke antrean dan dikirim ulang ke nomor penerima. Pastikan gerbang WhatsApp sudah tersambung.')
                    ->modalSubmitActionLabel('Kirim Ulang')
                    ->visible(fn ($record) => static::dapatDikirimUlang($record))
                    ->action(function ($record) {
                        static::kirimUlang($record);

                        Notification::make()
                            ->title('Notifikasi dikembalikan ke antrean')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('kirimUlangTerpilih')
                    ->label('Kirim Ulang yang Gagal')
                    ->icon('heroicon-m-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Kirim ulang notifikasi yang gagal')
                    ->modalDescription('Hanya baris berkanal WhatsApp yang berstatus gagal yang akan dikirim ulang; baris lain dilewati.')
                    ->deselectRecordsAfterCompletion()
                    ->action(function ($records) {
                        // Penyaringan dilakukan di sini, bukan dengan
                        // menyembunyikan aksinya, agar pengguna dapat menyapu
                        // pilihan tanpa harus memilah sendiri baris mana yang
                        // memang dapat dikirim ulang.
                        $jumlah = collect($records)
                            ->filter(fn ($record) => static::dapatDikirimUlang($record))
                            ->each(fn ($record) => static::kirimUlang($record))
                            ->count();

                        Notification::make()
                            ->title($jumlah > 0
                                ? "{$jumlah} notifikasi dikembalikan ke antrean"
                                : 'Tidak ada baris yang dapat dikirim ulang')
                            ->color($jumlah > 0 ? 'success' : 'warning')
                            ->send();
                    }),
            ]);
    }

    /**
     * Hanya pesan WhatsApp yang gagal yang boleh diulang.
     *
     * Notifikasi dalam aplikasi tidak pernah dikirim ke mana-mana sehingga
     * tidak ada yang perlu diulang, sedangkan pesan yang sudah terkirim tidak
     * boleh diulang agar penerima tidak menerima pesan yang sama dua kali.
     */
    protected static function dapatDikirimUlang(Notifikasi $notifikasi): bool
    {
        return $notifikasi->channel === 'whatsapp' && $notifikasi->status_kirim === 'gagal';
    }

    /**
     * Mengembalikan satu notifikasi ke antrean.
     *
     * Status dikembalikan ke menunggu dan alasan kegagalan sebelumnya dihapus,
     * sebab job pengiriman hanya menggarap baris berstatus menunggu — aturan
     * yang sama yang mencegah percobaan ulang mengirim pesan ganda.
     */
    protected static function kirimUlang(Notifikasi $notifikasi): void
    {
        $notifikasi->update([
            'status_kirim'  => 'pending',
            'error_message' => null,
            'dikirim_at'    => null,
        ]);

        KirimPesanWhatsApp::dispatch($notifikasi->id)->afterCommit();
    }

    /** Permintaan barang yang sudah mencapai status akhir. */
    protected function tabelPermintaan(Table $table): Table
    {
        return $table
            ->query(fn () => PermintaanBarangResource::getEloquentQuery()
                ->whereIn('status', PermintaanBarang::STATUS_RIWAYAT))
            ->deferFilters(false)
            ->defaultSort('created_at', 'desc')
            // Rincian dibuka sebagai dialog, sama seperti pada daftar
            // Permintaan Barang, agar cara membuka rincian tidak berbeda
            // antara permintaan yang masih berjalan dan yang sudah menjadi
            // riwayat. Aksinya dipinjam dari resource agar isinya satu sumber.
            ->recordAction('detail')
            ->recordActions([PermintaanBarangResource::aksiDetail()])
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
