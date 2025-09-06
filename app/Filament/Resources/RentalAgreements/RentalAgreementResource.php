<?php

namespace App\Filament\Resources\RentalAgreements;

use App\Filament\Resources\RentalAgreements\Pages\CreateRentalAgreement;
use App\Filament\Resources\RentalAgreements\Pages\EditRentalAgreement;
use App\Filament\Resources\RentalAgreements\Pages\ListRentalAgreements;
use App\Filament\Resources\RentalAgreements\Pages\ViewRentalAgreement;
use App\Filament\Resources\RentalAgreements\Schemas\RentalAgreementForm;
use App\Filament\Resources\RentalAgreements\Schemas\RentalAgreementInfolist;
use App\Filament\Resources\RentalAgreements\Tables\RentalAgreementsTable;
use App\Models\RentalAgreement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
// use Illuminate\Database\Eloquent\SoftDeletingScope;

class RentalAgreementResource extends Resource
{
    protected static ?string $model = RentalAgreement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'RentalAgreement';
    protected static ?string $navigationParentItem = 'Rent Management';


    public static function form(Schema $schema): Schema
    {
        return RentalAgreementForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RentalAgreementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RentalAgreementsTable::configure($table);
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
            'index' => ListRentalAgreements::route('/'),
            'create' => CreateRentalAgreement::route('/create'),
            'view' => ViewRentalAgreement::route('/{record}'),
            'edit' => EditRentalAgreement::route('/{record}/edit'),
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
