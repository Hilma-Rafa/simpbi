<?php

namespace App\Filament\Resources\AsetTetaps;

use App\Filament\Resources\AsetTetaps\Pages\CreateAsetTetap;
use App\Filament\Resources\AsetTetaps\Pages\EditAsetTetap;
use App\Filament\Resources\AsetTetaps\Pages\ListAsetTetaps;
use App\Filament\Resources\AsetTetaps\Schemas\AsetTetapForm;
use App\Filament\Resources\AsetTetaps\Tables\AsetTetapsTable;
use App\Models\AsetTetap;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AsetTetapResource extends Resource
{
    protected static ?string $model = AsetTetap::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'nama_aset';

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'kasubbag']);
    }
    
    public static function form(Schema $schema): Schema
    {
        return AsetTetapForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AsetTetapsTable::configure($table);
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
            'index' => ListAsetTetaps::route('/'),
            'create' => CreateAsetTetap::route('/create'),
            'edit' => EditAsetTetap::route('/{record}/edit'),
        ];
    }
}
