<?php

namespace App\Filament\Resources\AsetTetaps\Tables;

use App\Filament\Resources\AsetTetaps\AsetTetapResource;
use App\Filament\Support\AksiHapusTerlindung;
use App\Filament\Support\AksiImpor;
use App\Filament\Support\KeadaanKosong;
use App\Services\Impor\ImporAsetTetap;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use App\Filament\Support\AksiUbah;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AsetTetapsTable
{
    public const KONDISI_LABEL = [
        'baik'         => 'Baik',
        'rusak_ringan' => 'Rusak Ringan',
        'rusak_berat'  => 'Rusak Berat',
    ];

    protected const KONDISI_COLOR = [
        'baik'         => 'success',
        'rusak_ringan' => 'warning',
        'rusak_berat'  => 'danger',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nama_aset')
            /** Lihat catatan yang sama pada tabel Barang Persediaan. */
            ->emptyStateIcon('heroicon-o-cube')
            ->emptyStateHeading(fn ($livewire): string => KeadaanKosong::sedangDisaring($livewire)
                ? 'Tidak ada aset yang cocok'
                : 'Belum ada aset tetap tercatat')
            ->emptyStateDescription(fn ($livewire): string => KeadaanKosong::sedangDisaring($livewire)
                ? 'Coba longgarkan penyaringnya, atau periksa kembali ejaan kata yang dicari.'
                : 'Aset yang dikelola Sub-Bagian Umum akan tampil di sini beserta kondisi dan tim kerja tempatnya ditempatkan.')
            ->columns([
                TextColumn::make('nama_aset')
                    ->label('Nama Aset')
                    ->description(fn ($record) => 'NUP ' . $record->nup)
                    ->searchable(['nama_aset', 'nup'])
                    ->sortable()
                    ->wrap(),

                TextColumn::make('kategori.nama_kategori')
                    ->label('Kategori')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('timPenempatan.nama_tim')
                    ->label('Tim Kerja')
                    ->placeholder('Belum ditempatkan')
                    ->toggleable(),

                self::kolomKondisi(),

                /** Lihat catatan yang sama pada tabel Barang Persediaan. */
                IconColumn::make('status_aktif')
                    ->label('Aktif')
                    ->boolean()
                    ->trueColor('gray')
                    ->falseColor('danger'),

                TextColumn::make('sumber_data')
                    ->label('Sumber Data')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => $state === 'api' ? 'API' : ucfirst((string) $state))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('synced_at')
                    ->label('Tersinkron')
                    ->dateTime('d-m-Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d-m-Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('kategori_id')
                    ->label('Kategori')
                    ->relationship('kategori', 'nama_kategori')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('kondisi')
                    ->label('Kondisi')
                    ->options(self::KONDISI_LABEL),

                SelectFilter::make('tim_penempatan_id')
                    ->label('Tim Kerja')
                    ->relationship('timPenempatan', 'nama_tim'),

                TernaryFilter::make('status_aktif')
                    ->label('Status')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
            ])
            ->recordActions([
                self::aksiRiwayatPenempatan(),

                AksiUbah::buat(),
            ])
            /*
             * Sinkronisasi berkala satu arah berbasis berkas.
             *
             * Memakai aksi impor yang sudah dipakai Tim Kerja dan Pengguna,
             * bukan menu tersendiri: berkasnya diunggah di tempat datanya
             * dibaca, dan tidak ada satu pun bagian antarmuka yang perlu
             * dipelajari ulang. Hak aksesnya mengikuti AsetTetapResource.
             */
            ->headerActions([
                AksiImpor::buat(
                    judul: ImporAsetTetap::JUDUL,
                    kolom: ImporAsetTetap::kolom(),
                    namaTemplate: 'Template-Sinkronisasi-Aset-Tetap.xlsx',
                    impor: fn (string $lintasan) => app(ImporAsetTetap::class)->jalankan($lintasan),
                ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    AksiHapusTerlindung::massal(AsetTetapResource::ALASAN_TAK_DAPAT_DIHAPUS),
                ]),
            ]);
    }

    /**
     * Aset berkondisi baik merupakan mayoritas, sehingga bila setiap baris
     * dilencanai hijau warnanya berhenti berarti. Lencana karena itu
     * disediakan hanya untuk aset yang rusak.
     *
     * Diambil terpisah dari configure() agar kolom yang sama persis dapat
     * dipakai ulang pada halaman Aset Tetap Tim Saya (bacaan saja).
     */
    public static function kolomKondisi(): TextColumn
    {
        return TextColumn::make('kondisi')
            ->label('Kondisi')
            ->formatStateUsing(fn (string $state): string => self::KONDISI_LABEL[$state] ?? $state)
            ->badge(fn (string $state): bool => $state !== 'baik')
            ->color(fn (string $state): ?string => $state === 'baik'
                ? null
                : (self::KONDISI_COLOR[$state] ?? 'gray'));
    }

    /**
     * Jejak penempatan satu aset, dibaca sebagai dialog di atas daftar
     * mengikuti pola yang sudah dipakai Kartu Kendali pada tabel Barang
     * Persediaan. Halaman tersendiri tidak diadakan: riwayat ini dibuka
     * sebentar untuk memeriksa satu aset lalu ditutup lagi, dan berpindah
     * halaman hanya akan memutus posisi gulir serta penyaring yang sedang
     * berlaku.
     *
     * Tombolnya tetap tampil bagi aset yang belum berriwayat. Tombol yang
     * hilang tanpa keterangan membuat orang mengira datanya rusak, sedangkan
     * dialog yang terbuka lalu menerangkan mengapa riwayatnya kosong justru
     * mengajarkan aturan yang berlaku — sama dengan sikap yang diambil aksi
     * hapus terlindung.
     *
     * Dipakai ulang pada halaman Aset Tetap Tim Saya, sebab Ketua Tim dan Tim
     * berhak membaca riwayat penempatan asetnya walau tidak berhak menyunting
     * data induknya.
     */
    public static function aksiRiwayatPenempatan(): Action
    {
        return Action::make('riwayatPenempatan')
            ->label('Riwayat Penempatan')
            ->icon('heroicon-m-map-pin')
            ->color('gray')
            ->outlined()
            ->modalHeading(fn ($record): string => 'Riwayat Penempatan — ' . $record->nama_aset)
            ->modalWidth('3xl')
            // Dialog ini hanya dibaca, sehingga tidak punya tombol kirim.
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->modalContent(fn ($record) => view(
                'filament.partials.riwayat-penempatan-aset',
                [
                    'aset' => $record,
                    // Relasi dimuat di sini, bukan pada kueri tabel, agar
                    // daftar tidak menanggung kueri riwayat untuk baris
                    // yang tidak pernah dibuka.
                    'riwayat' => $record->riwayatPenempatan()->with(['tim', 'bast'])->get(),
                ],
            ));
    }
}
