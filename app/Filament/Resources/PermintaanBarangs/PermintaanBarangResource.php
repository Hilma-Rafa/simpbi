<?php

namespace App\Filament\Resources\PermintaanBarangs;

use App\Filament\Resources\PermintaanBarangs\Pages;
use App\Filament\Support\KeadaanKosong;
use App\Models\PermintaanBarang;
use App\Support\JamKerja;
use App\Models\RiwayatPersetujuan;
use App\Services\StokService;
use App\Services\DokumenPermintaanService;
use App\Services\NotifikasiService;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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

                TextColumn::make('tim.nama_tim')
                    ->label('Tim Pemohon')
                    ->searchable(),

                TextColumn::make('nama_pemohon')
                    ->label('Pemohon'),

                TextColumn::make('detail_count')
                    ->label('Jumlah Item')
                    ->counts('detail'),

                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->date('d-m-Y'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => PermintaanBarang::STATUS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'selesai'                           => 'success',
                        'ditolak_ketua', 'ditolak_kasubbag' => 'danger',
                        'bermasalah', 'kedaluwarsa'         => 'gray',
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
            // Rincian dibuka dengan mengklik barisnya, menggantikan tombol
            // "Detail" yang sebelumnya ada pada setiap baris. Filament memberi
            // baris penunjuk tangan dan sorotan ketika ditunjuk, dan klik pada
            // tombol aksi tidak ikut membuka halaman rincian.
            ->recordUrl(fn ($record) => static::getUrl('detail', ['record' => $record]))
            ->recordActions([

                // ---------- TAHAP 1 : PERSETUJUAN KETUA TIM ----------

                Action::make('setujui')
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
                    ->action(fn (array $data, $record) => static::setujuiKetua($record, $data['catatan'] ?? null)),

                Action::make('tolak')
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
                    ->action(fn (array $data, $record) => static::tolakKetua($record, $data['catatan'])),

                // ---------- TAHAP 2 : VERIFIKASI KETERSEDIAAN FISIK ----------

                Action::make('verifikasi')
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
                    ->action(fn (array $data, $record) => static::simpanVerifikasi($record, $data)),

                // ---------- TAHAP 3 : PERSETUJUAN AKHIR KASUBBAG UMUM ----------

                Action::make('setujuiKasubbag')
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

                                TextInput::make('jumlah_final')
                                    ->label('Disetujui')
                                    ->numeric()
                                    ->minValue(0)
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
                    ->action(fn (array $data, $record) => static::setujuiKasubbag($record, $data)),

                Action::make('tolakKasubbag')
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
                    ->action(fn (array $data, $record) => static::tolakKasubbag($record, $data['catatan'])),

                // ---------- TAHAP 4 : PENYIAPAN BARANG ----------

                Action::make('siapkan')
                    ->label('Barang Siap Diambil')
                    ->icon('heroicon-m-archive-box')
                    ->color('info')
                    ->button()
                    ->visible(fn ($record) => auth()->user()->role === 'petugas_gudang'
                        && $record->status === 'siap_diproses')
                    ->modalHeading('Penyiapan Barang')
                    ->modalDescription('Tandai bahwa barang telah disiapkan dan dapat diambil oleh unit pemohon.')
                    ->modalSubmitActionLabel('Tandai Siap Diambil')
                    ->modalCancelActionLabel('Batal')
                    ->action(fn ($record) => static::tandaiSiapDiambil($record)),

                // ---------- TAHAP 5 : KONFIRMASI PENERIMAAN ----------

                Action::make('konfirmasi')
                    ->label('Konfirmasi Penerimaan')
                    ->icon('heroicon-m-hand-thumb-up')
                    ->color('success')
                    ->button()
                    ->visible(fn ($record) => in_array(auth()->user()->role, ['tim', 'ketua_tim'])
                        && $record->status === 'siap_diambil')
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
                    ])
                    ->action(fn (array $data, $record) => static::konfirmasiPenerimaan($record, $data)),

                    // ---------- TAHAP 6 : PENGESAHAN AKHIR ----------
 
                Action::make('sahkan')
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
                    ->schema([
                        Textarea::make('catatan')->label('Catatan (opsional)')->rows(2),
                    ])
                    ->action(fn (array $data, $record) => static::sahkan($record, $data['catatan'] ?? null)),
 
                // ---------- UNDUH DOKUMEN ----------

                static::aksiUnduhBukti()->size('sm'),
            ])
            ->recordActionsAlignment('right');
    }

    /**
     * Tombol pengunduhan dokumen bukti permintaan.
     *
     * Didefinisikan satu kali dan dipakai baik pada baris tabel maupun pada
     * kepala halaman rincian, agar bentuknya seragam dengan tombol aksi lain
     * pada sistem: tombol bergaris dengan warna utama SIMPBI, bukan tautan
     * abu-abu yang tampak berbeda sendiri di antara tombol alur persetujuan.
     */
    public static function aksiUnduhBukti(): Action
    {
        return Action::make('unduhBukti')
            ->label('Unduh Bukti')
            ->icon('heroicon-m-arrow-down-tray')
            ->iconPosition(\Filament\Support\Enums\IconPosition::Before)
            ->color('primary')
            ->button()
            ->outlined()
            ->tooltip('Unduh dokumen bukti permintaan yang telah disahkan')
            ->visible(fn ($record) => filled($record?->file_bukti_path))
            ->url(fn ($record) => route('bukti.unduh', $record))
            ->openUrlInNewTab();
    }

    // =====================================================================
    // TINDAKAN PADA ALUR PERSETUJUAN
    // =====================================================================

    /** Menyetujui pada tahap Ketua Tim (BPMN P.2.9). */
    protected static function setujuiKetua(PermintaanBarang $record, ?string $catatan): void
    {
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

    /** Menandai barang telah disiapkan dan siap diambil (BPMN P.2.S8). */
    protected static function tandaiSiapDiambil(PermintaanBarang $record): void
    {
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
            ->body('Unit pemohon dapat mengambil barang di gudang.')
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
        $sesuai       = ($data['sesuai'] ?? 'ya') === 'ya';
        $dapatDiatasi = ($data['dapat_diatasi'] ?? '1') === '1';

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

    /** Mengambil batas waktu tahapan dari tabel pengaturan. */
    protected static function batasJam(string $kunci, int $bawaan = 24): int
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