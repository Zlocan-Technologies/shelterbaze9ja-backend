<?php

namespace App\Filament\Resources\AgentAssignments\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AgentAssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('agent_id')
                    ->required()
                    ->numeric(),
                TextInput::make('landlord_id')
                    ->numeric(),
                TextInput::make('property_id')
                    ->numeric(),
                Select::make('assignment_type')
                    ->options(['landlord_support' => 'Landlord support', 'property_verification' => 'Property verification'])
                    ->required(),
                Select::make('status')
                    ->options(['active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled'])
                    ->default('active')
                    ->required(),
                TextInput::make('assigned_by')
                    ->required()
                    ->numeric(),
                Textarea::make('assignment_notes')
                    ->columnSpanFull(),
                DateTimePicker::make('completed_at'),
            ]);
    }
}
