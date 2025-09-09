<?php

namespace App\Filament\Resources\RentSavings;

use App\Filament\Resources\RentSavings\Pages\CreateRentSaving;
use App\Filament\Resources\RentSavings\Pages\EditRentSaving;
use App\Filament\Resources\RentSavings\Pages\ListRentSavings;
use App\Filament\Resources\RentSavings\Pages\ViewRentSaving;
use App\Filament\Resources\RentSavings\Schemas\RentSavingForm;
use App\Filament\Resources\RentSavings\Schemas\RentSavingInfolist;
use App\Filament\Resources\RentSavings\Tables\RentSavingsTable;
use App\Models\RentSaving;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
// use Illuminate\Database\Eloquent\SoftDeletingScope;

class RentSavingResource extends Resource
{
    protected static ?string $model = RentSaving::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'RentSaving';
    protected static ?string $navigationParentItem = 'Rent Management';

    public static function form(Schema $schema): Schema
    {
        return RentSavingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RentSavingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RentSavingsTable::configure($table);
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
            'index' => ListRentSavings::route('/'),
            'create' => CreateRentSaving::route('/create'),
            'view' => ViewRentSaving::route('/{record}'),
            'edit' => EditRentSaving::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                // SoftDeletingScope::class,
            ]);
    }
}
