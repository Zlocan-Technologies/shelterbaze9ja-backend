<?php

namespace App\Filament\Resources\EngagementFees;

use App\Filament\Resources\EngagementFees\Pages\CreateEngagementFee;
use App\Filament\Resources\EngagementFees\Pages\EditEngagementFee;
use App\Filament\Resources\EngagementFees\Pages\ListEngagementFees;
use App\Filament\Resources\EngagementFees\Pages\ViewEngagementFee;
use App\Filament\Resources\EngagementFees\Schemas\EngagementFeeForm;
use App\Filament\Resources\EngagementFees\Schemas\EngagementFeeInfolist;
use App\Filament\Resources\EngagementFees\Tables\EngagementFeesTable;
use App\Models\EngagementFee;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
// use Illuminate\Database\Eloquent\SoftDeletingScope;

class EngagementFeeResource extends Resource
{
    protected static ?string $model = EngagementFee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'EngagementFee';

    public static function form(Schema $schema): Schema
    {
        return EngagementFeeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EngagementFeeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EngagementFeesTable::configure($table);
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
            'index' => ListEngagementFees::route('/'),
            'create' => CreateEngagementFee::route('/create'),
            'view' => ViewEngagementFee::route('/{record}'),
            'edit' => EditEngagementFee::route('/{record}/edit'),
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
