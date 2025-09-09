<?php

namespace App\Filament\Resources\SupportTickets\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SupportTicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('property_id')
                    ->numeric(),
                TextInput::make('rental_agreement_id')
                    ->numeric(),
                TextInput::make('ticket_number')
                    ->required(),
                Select::make('ticket_type')
                    ->options([
            'general' => 'General',
            'property_issue' => 'Property issue',
            'payment_issue' => 'Payment issue',
            'technical' => 'Technical',
            'account_issue' => 'Account issue',
        ])
                    ->required(),
                TextInput::make('subject')
                    ->required(),
                Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                Select::make('status')
                    ->options([
            'open' => 'Open',
            'in_progress' => 'In progress',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
        ])
                    ->default('open')
                    ->required(),
                Select::make('priority')
                    ->options(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'])
                    ->default('medium')
                    ->required(),
                TextInput::make('assigned_to')
                    ->numeric(),
                TextInput::make('attachments'),
                Textarea::make('resolution_notes')
                    ->columnSpanFull(),
                DateTimePicker::make('resolved_at'),
            ]);
    }
}
