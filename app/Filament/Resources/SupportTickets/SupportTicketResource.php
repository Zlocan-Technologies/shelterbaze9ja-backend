<?php

namespace App\Filament\Resources\SupportTickets;

use App\Filament\Resources\SupportTickets\Pages\CreateSupportTicket;
use App\Filament\Resources\SupportTickets\Pages\EditSupportTicket;
use App\Filament\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Filament\Resources\SupportTickets\Pages\ViewSupportTicket;
use App\Filament\Resources\SupportTickets\Schemas\SupportTicketForm;
use App\Filament\Resources\SupportTickets\Schemas\SupportTicketInfolist;
use App\Filament\Resources\SupportTickets\Tables\SupportTicketsTable;
use App\Models\SupportTicket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $recordTitleAttribute = 'ticket_number';
    
    protected static ?string $modelLabel = 'Support Ticket';
    
    protected static ?string $pluralModelLabel = 'Support Tickets';
    
    protected static string | UnitEnum | null $navigationGroup = 'Customer Support';

    protected static ?string $navigationLabel = 'Support Tickets';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return SupportTicketForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SupportTicketInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupportTicketsTable::configure($table);
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
            'index' => ListSupportTickets::route('/'),
            'create' => CreateSupportTicket::route('/create'),
            'view' => ViewSupportTicket::route('/{record}'),
            'edit' => EditSupportTicket::route('/{record}/edit'),
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
            'ticket_number',
            'subject',
            'description',
            'user.first_name',
            'user.last_name',
            'property.title',
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereIn('status', ['open', 'in_progress'])->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $openCount = static::getModel()::where('status', 'open')->count();
        $highPriorityCount = static::getModel()::where('priority', 'high')
            ->whereIn('status', ['open', 'in_progress'])->count();
        
        if ($highPriorityCount > 0) {
            return 'danger';
        } elseif ($openCount > 0) {
            return 'warning';
        }
        
        return 'success';
    }
}
