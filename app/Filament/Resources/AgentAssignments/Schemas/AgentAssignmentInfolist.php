<?php

namespace App\Filament\Resources\AgentAssignments\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AgentAssignmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('agent_id')
                    ->numeric(),
                TextEntry::make('landlord_id')
                    ->numeric(),
                TextEntry::make('property_id')
                    ->numeric(),
                TextEntry::make('assignment_type'),
                TextEntry::make('status'),
                TextEntry::make('assigned_by')
                    ->numeric(),
                TextEntry::make('completed_at')
                    ->dateTime(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
