<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\CreateAuditLog;
use App\Filament\Resources\AuditLogs\Pages\EditAuditLog;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\AuditLogs\Pages\ViewAuditLog;
use App\Filament\Resources\AuditLogs\Schemas\AuditLogForm;
use App\Filament\Resources\AuditLogs\Schemas\AuditLogInfolist;
use App\Filament\Resources\AuditLogs\Tables\AuditLogsTable;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEye;

    protected static ?string $recordTitleAttribute = 'id';
    
    protected static ?string $modelLabel = 'Audit Log';
    
    protected static ?string $pluralModelLabel = 'Audit Logs';
    
    protected static string | UnitEnum | null $navigationGroup = 'System Management';

    protected static ?string $navigationLabel = 'Audit Logs';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return AuditLogForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AuditLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AuditLogsTable::configure($table);
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
            'index' => ListAuditLogs::route('/'),
            'view' => ViewAuditLog::route('/{record}'),
            // Create and Edit removed - audit logs should not be manually created/edited
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                // SoftDeletingScope::class,
            ])
            ->orderBy('created_at', 'desc'); // Show newest first
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'user.first_name',
            'user.last_name',
            'action',
            'model_type',
            'ip_address',
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false; // Audit logs should not be manually created
    }

    public static function canEdit($record): bool
    {
        return false; // Audit logs should not be edited
    }

    public static function canDelete($record): bool
    {
        return false; // Audit logs should not be deleted
    }
}
