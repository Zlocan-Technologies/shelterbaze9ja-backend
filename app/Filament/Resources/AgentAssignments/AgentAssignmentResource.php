<?php

namespace App\Filament\Resources\AgentAssignments;

use App\Filament\Resources\AgentAssignments\Pages\CreateAgentAssignment;
use App\Filament\Resources\AgentAssignments\Pages\EditAgentAssignment;
use App\Filament\Resources\AgentAssignments\Pages\ListAgentAssignments;
use App\Filament\Resources\AgentAssignments\Pages\ViewAgentAssignment;
use App\Filament\Resources\AgentAssignments\Schemas\AgentAssignmentForm;
use App\Filament\Resources\AgentAssignments\Schemas\AgentAssignmentInfolist;
use App\Filament\Resources\AgentAssignments\Tables\AgentAssignmentsTable;
use App\Models\AgentAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
// use Illuminate\Database\Eloquent\SoftDeletingScope;

class AgentAssignmentResource extends Resource
{
    protected static ?string $model = AgentAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'AgentAssignment';
    protected static ?string $navigationParentItem = 'Property';


    public static function form(Schema $schema): Schema
    {
        return AgentAssignmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AgentAssignmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AgentAssignmentsTable::configure($table);
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
            'index' => ListAgentAssignments::route('/'),
            'create' => CreateAgentAssignment::route('/create'),
            'view' => ViewAgentAssignment::route('/{record}'),
            'edit' => EditAgentAssignment::route('/{record}/edit'),
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
