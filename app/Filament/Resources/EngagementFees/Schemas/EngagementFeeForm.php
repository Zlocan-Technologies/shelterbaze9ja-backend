<?php

namespace App\Filament\Resources\EngagementFees\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EngagementFeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('property_id')
                    ->required()
                    ->numeric(),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('payment_reference')
                    ->required(),
                Select::make('payment_status')
                    ->options(['pending' => 'Pending', 'completed' => 'Completed', 'failed' => 'Failed'])
                    ->default('pending')
                    ->required(),
                TextInput::make('payment_method')
                    ->required()
                    ->default('paystack'),
                TextInput::make('payment_data'),
                DateTimePicker::make('paid_at'),
            ]);
    }
}
