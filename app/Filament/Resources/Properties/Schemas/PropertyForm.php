<?php

namespace App\Filament\Resources\Properties\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PropertyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('landlord_id')
                    ->required()
                    ->numeric(),
                TextInput::make('agent_id')
                    ->numeric(),
                TextInput::make('title')
                    ->required(),
                Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                Select::make('property_type')
                    ->options([
            '1_bedroom' => '1 bedroom',
            '2_bedroom' => '2 bedroom',
            '3_bedroom' => '3 bedroom',
            '4_bedroom' => '4 bedroom',
            'studio' => 'Studio',
            'duplex' => 'Duplex',
            'bungalow' => 'Bungalow',
        ])
                    ->required(),
                TextInput::make('rent_amount')
                    ->required()
                    ->numeric(),
                TextInput::make('shelterbaze_commission')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('total_amount')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                Textarea::make('location_address')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('state')
                    ->required(),
                TextInput::make('lga')
                    ->required(),
                TextInput::make('longitude')
                    ->numeric(),
                TextInput::make('latitude')
                    ->numeric(),
                TextInput::make('facilities'),
                Select::make('status')
                    ->options(['open' => 'Open', 'closed' => 'Closed', 'rented' => 'Rented'])
                    ->default('open')
                    ->required(),
                Select::make('verification_status')
                    ->options(['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected'])
                    ->default('pending')
                    ->required(),
                TextInput::make('verified_by')
                    ->numeric(),
                DateTimePicker::make('verified_at'),
            ]);
    }
}
