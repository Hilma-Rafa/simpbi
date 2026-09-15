<?php

namespace App\Filament\Resources\BarangPersediaans;

use App\Filament\Resources\BarangPersediaans\Pages\CreateBarangPersediaan;
use App\Filament\Resources\BarangPersediaans\Pages\EditBarangPersediaan;
use App\Filament\Resources\BarangPersediaans\Pages\ListBarangPersediaans;
use App\Filament\Resources\BarangPersediaans\Schemas\BarangPersediaanForm;
use App\Filament\Resources\BarangPersediaans\Tables\BarangPersediaansTable;
use App\Models\BarangPersediaan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BarangPersediaanResource extends Resource
{
    protected static ?string $model = BarangPersediaan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|\UnitEnum|null $navigationGroup = 'Persediaan';

    protected static ?string $navigationLabel = 'Barang Persediaan';

    protected static ?string $modelLabel = 'Barang Persediaan';

    protected static ?string $pluralModelLabel = 'Barang Persediaan';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'nama_barang';

    /**
     * Keterangan yang dibaca pengguna ketika penghapusan ditolak.
     *
     * Ditaruh di sini, bukan di dalam masing-masing aksi, supaya halaman sunting
     * dan aksi massal pada tabel tidak pernah menerangkan hal yang berbeda untuk
     * penolakan yang sama.
     */
    public const ALASAN_TAK_DAPAT_DIHAPUS =
        'Barang yang sudah memiliki riwayat mutasi stok tidak dapat dihapus, sebab riwayat itulah '
        . 'sumber Kartu Kendali dan akan ikut terhapus bersamanya. Nonaktifkan barangnya lewat '
        . 'Status Aktif agar tidak lagi muncul pada katalog permintaan, sementara kartunya tetap '
        . 'dapat diterbitkan.';

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'kasubbag']);
    }
    
    public static function form(Schema $schema): Schema
    {
        return BarangPersediaanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BarangPersediaansTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBarangPersediaans::route('/'),
            'create' => CreateBarangPersediaan::route('/create'),
            'edit' => EditBarangPersediaan::route('/{record}/edit'),
        ];
    }
}
