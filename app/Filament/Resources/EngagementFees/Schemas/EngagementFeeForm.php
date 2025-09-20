<?php

namespace App\Filament\Resources\EngagementFees\Schemas;

use App\Traits\GenerateReference;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EngagementFeeForm
{
    use GenerateReference;
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->required(),
                Select::make('property_id')
                    ->relationship('property', 'title')
                    ->searchable()
                    ->required(),

                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('payment_reference')
                    ->default(fn() => self::generateReference('ENG'))
                    ->disabled(),
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
