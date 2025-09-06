<?php

namespace App\Filament\Resources\RentPayments;

use App\Filament\Resources\RentPayments\Pages\CreateRentPayment;
use App\Filament\Resources\RentPayments\Pages\EditRentPayment;
use App\Filament\Resources\RentPayments\Pages\ListRentPayments;
use App\Filament\Resources\RentPayments\Pages\ViewRentPayment;
use App\Filament\Resources\RentPayments\Schemas\RentPaymentForm;
use App\Filament\Resources\RentPayments\Schemas\RentPaymentInfolist;
use App\Filament\Resources\RentPayments\Tables\RentPaymentsTable;
use App\Models\RentPayment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
// use Illuminate\Database\Eloquent\SoftDeletingScope;

class RentPaymentResource extends Resource
{
    protected static ?string $model = RentPayment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'RentPayment';
    protected static ?string $navigationParentItem = 'Rent Management';



    public static function form(Schema $schema): Schema
    {
        return RentPaymentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RentPaymentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RentPaymentsTable::configure($table);
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
            'index' => ListRentPayments::route('/'),
            'create' => CreateRentPayment::route('/create'),
            'view' => ViewRentPayment::route('/{record}'),
            'edit' => EditRentPayment::route('/{record}/edit'),
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
