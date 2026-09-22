<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AsetTetaps\Tables\AsetTetapsTable;
use App\Models\AsetTetap;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * "Aset tetap apa saja yang saat ini berada pada tim saya?" — bacaan saja
 * bagi Ketua Tim dan Tim.
 *
 * Bukan salinan AsetTetapResource: peran ini tidak berwenang atas data induk
 * aset tetap (Instruksi §4), sehingga tidak ada tombol Buat, Ubah, Hapus, atau
 * impor sinkronisasi di sini — hanya daftar aset aktif yang sedang ditempatkan
 * pada tim penggunanya, dibaca dari kolom penempatan yang sama dipakai
 * AsetTetapResource, bukan mekanisme penempatan baru.
 */
class AsetTetapTimSaya extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.aset-tetap-tim-saya';

    // Berbeda dari ikon AsetTetapResource (OutlinedCpuChip) walau sama-sama
    // membahas aset tetap, supaya keduanya tidak tampak sebagai menu kembar
    // pada peran yang kebetulan membuka dokumentasi keduanya.
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventaris';

    protected static ?string $navigationLabel = 'Aset Tetap Tim Saya';

    protected static ?string $title = 'Aset Tetap Tim Saya';

    // Setelah Mutasi Aset, sebagaimana pembagian akses pada Instruksi §4:
    // Ketua Tim dan Tim membuka Mutasi Aset lebih dulu, baru daftar aset ini.
    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['ketua_tim', 'tim']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AsetTetap::query()
                    ->where('status_aktif', true)
                    // Pengguna tanpa tim tidak melihat aset apa pun; tanpa ini kolom
                    // dibandingkan dengan NULL dan yang tampil aset yang belum ditempatkan.
                    ->when(
                        auth()->user()?->tim_id,
                        fn ($query, $timId) => $query->where('tim_penempatan_id', $timId),
                        fn ($query) => $query->whereRaw('1 = 0'),
                    )
                    ->with([
                        'kategori',
                        // Dibatasi pada baris yang sedang berlaku, sehingga
                        // kolom Mulai Penempatan tidak perlu kueri per baris.
                        'riwayatPenempatan' => fn ($q) => $q->whereNull('tanggal_selesai'),
                    ])
            )
            ->defaultSort('nama_aset')
            ->emptyStateIcon('heroicon-o-cube')
            ->emptyStateHeading('Belum ada aset yang ditempatkan')
            ->emptyStateDescription('Belum ada aset tetap yang ditempatkan pada tim Anda.')
            ->columns([
                TextColumn::make('nama_aset')
                    ->label('Nama Aset')
                    ->description(fn ($record) => 'NUP ' . $record->nup)
                    ->searchable(['nama_aset', 'nup'])
                    ->sortable()
                    ->wrap(),

                TextColumn::make('kategori.nama_kategori')
                    ->label('Kategori')
                    ->placeholder('—'),

                AsetTetapsTable::kolomKondisi(),

                TextColumn::make('mulai_penempatan')
                    ->label('Mulai Penempatan')
                    ->state(fn (AsetTetap $record) => $record->riwayatPenempatan->first()?->tanggal_mulai)
                    ->date('d-m-Y')
                    ->placeholder('—'),
            ])
            ->recordActions([
                AsetTetapsTable::aksiRiwayatPenempatan(),
            ]);
    }
}
