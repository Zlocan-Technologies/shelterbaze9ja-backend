<?php

namespace App\Filament\Resources\RentSavings\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RentSavingForm
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
                TextInput::make('plan_name')
                    ->required(),
                TextInput::make('target_amount')
                    ->required()
                    ->numeric(),
                TextInput::make('current_amount')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                DatePicker::make('due_date')
                    ->required(),
                Select::make('status')
                    ->options(['active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled'])
                    ->default('active')
                    ->required(),
                TextInput::make('early_withdrawal_penalty')
                    ->required()
                    ->numeric()
                    ->default(5.0),
                TextInput::make('deposit_charge')
                    ->required()
                    ->numeric()
                    ->default(2.0),
                Toggle::make('is_external_property')
                    ->required(),
                Textarea::make('external_property_details')
                    ->columnSpanFull(),
            ]);
    }
}
