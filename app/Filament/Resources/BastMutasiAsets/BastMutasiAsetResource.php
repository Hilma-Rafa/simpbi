<?php

namespace App\Filament\Resources\BastMutasiAsets;

use App\Filament\Resources\BastMutasiAsets\Pages\CreateBastMutasiAset;
use App\Filament\Resources\BastMutasiAsets\Pages\ListBastMutasiAsets;
use App\Filament\Resources\BastMutasiAsets\Schemas\BastMutasiAsetForm;
use App\Filament\Resources\BastMutasiAsets\Tables\BastMutasiAsetsTable;
use App\Models\BastMutasiAset;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Mutasi aset tetap melalui BAST (UC-16/17/18). Subsistem ini tidak memakai
 * alur approval permintaan barang (Instruksi §18): buat → sahkan → konfirmasi.
 */
class BastMutasiAsetResource extends Resource
{
    protected static ?string $model = BastMutasiAset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventaris';

    protected static ?string $navigationLabel = 'Mutasi Aset';

    protected static ?string $modelLabel = 'BAST Mutasi Aset';

    protected static ?string $pluralModelLabel = 'Mutasi Aset';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'nomor_bast';

    /** Petugas Gudang, Kasubbag, dan Ketua Tim terlibat (Instruksi §18, §21). */
    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['petugas_gudang', 'kasubbag', 'ketua_tim']);
    }

    /** Hanya Petugas Gudang yang membuat BAST (UC-16). */
    public static function canCreate(): bool
    {
        return auth()->user()?->role === 'petugas_gudang';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['aset', 'timAsal', 'timTujuan']);

        $user = auth()->user();

        // Ketua Tim hanya melihat mutasi yang melibatkan timnya (Instruksi §54).
        if ($user?->role === 'ketua_tim') {
            $query->where(function (Builder $q) use ($user): void {
                $q->where('tim_asal_id', $user->tim_id)
                    ->orWhere('tim_tujuan_id', $user->tim_id);
            });
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return BastMutasiAsetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BastMutasiAsetsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBastMutasiAsets::route('/'),
            'create' => CreateBastMutasiAset::route('/create'),
        ];
    }
}
