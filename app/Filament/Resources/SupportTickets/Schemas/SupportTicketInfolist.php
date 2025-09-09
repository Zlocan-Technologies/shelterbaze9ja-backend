<?php

namespace App\Filament\Resources\SupportTickets\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SupportTicketInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user_id')
                    ->numeric(),
                TextEntry::make('property_id')
                    ->numeric(),
                TextEntry::make('rental_agreement_id')
                    ->numeric(),
                TextEntry::make('ticket_number'),
                TextEntry::make('ticket_type'),
                TextEntry::make('subject'),
                TextEntry::make('status'),
                TextEntry::make('priority'),
                TextEntry::make('assigned_to')
                    ->numeric(),
                TextEntry::make('resolved_at')
                    ->dateTime(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
