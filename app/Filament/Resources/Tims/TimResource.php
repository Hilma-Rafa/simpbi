<?php

namespace App\Filament\Resources\Tims;

use App\Filament\Resources\Tims\Pages\CreateTim;
use App\Filament\Resources\Tims\Pages\EditTim;
use App\Filament\Resources\Tims\Pages\ListTims;
use App\Filament\Resources\Tims\Schemas\TimForm;
use App\Filament\Resources\Tims\Tables\TimsTable;
use App\Models\Tim;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TimResource extends Resource
{
    protected static ?string $model = Tim::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Administrasi';

    protected static ?string $navigationLabel = 'Tim';

    protected static ?string $modelLabel = 'Tim';

    protected static ?string $pluralModelLabel = 'Tim';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'nama_tim';

    /** Keterangan yang dibaca pengguna ketika penghapusan ditolak. */
    public const ALASAN_TAK_DAPAT_DIHAPUS =
        'Tim kerja yang sudah pernah mengajukan permintaan atau tercantum pada BAST mutasi aset '
        . 'tidak dapat dihapus, sebab namanya melekat pada dokumen yang sudah terbit. Nonaktifkan '
        . 'timnya lewat Status Aktif agar tidak lagi dapat dipilih, sementara riwayatnya tetap utuh.';

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'kasubbag']);
    }
    
    public static function form(Schema $schema): Schema
    {
        return TimForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TimsTable::configure($table);
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
            'index' => ListTims::route('/'),
            'create' => CreateTim::route('/create'),
            'edit' => EditTim::route('/{record}/edit'),
        ];
    }
}
