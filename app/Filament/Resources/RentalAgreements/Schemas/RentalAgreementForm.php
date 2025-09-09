<?php

namespace App\Filament\Resources\RentalAgreements\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class RentalAgreementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('property_id')
                    ->required()
                    ->numeric(),
                TextInput::make('tenant_id')
                    ->required()
                    ->numeric(),
                TextInput::make('landlord_id')
                    ->required()
                    ->numeric(),
                TextInput::make('agent_id')
                    ->numeric(),
                TextInput::make('rent_amount')
                    ->required()
                    ->numeric(),
                TextInput::make('shelterbaze_commission')
                    ->required()
                    ->numeric(),
                TextInput::make('total_amount')
                    ->required()
                    ->numeric(),
                DatePicker::make('agreement_start_date')
                    ->required(),
                DatePicker::make('agreement_end_date')
                    ->required(),
                Select::make('status')
                    ->options([
            'pending' => 'Pending',
            'active' => 'Active',
            'expired' => 'Expired',
            'terminated' => 'Terminated',
        ])
                    ->default('pending')
                    ->required(),
                Textarea::make('terms_conditions')
                    ->columnSpanFull(),
            ]);
    }
}
