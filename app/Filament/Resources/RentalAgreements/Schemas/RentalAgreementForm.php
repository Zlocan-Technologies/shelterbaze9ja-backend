<?php

namespace App\Filament\Resources\RentalAgreements\Schemas;

use App\Models\Property;
use App\Models\User;
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
                Select::make('property_id')
                    ->label('Property')
                    ->options(Property::query()->pluck('title', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('tenant_id')
                    ->label('Tenant')
                    ->options(User::where('role', 'user')->get()->mapWithKeys(function ($user) {
                        return [$user->id => $user->first_name . ' ' . $user->last_name];
                    }))
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('landlord_id')
                    ->label('Landlord')
                    ->options(User::where('role', 'landlord')->get()->mapWithKeys(function ($user) {
                        return [$user->id => $user->first_name . ' ' . $user->last_name];
                    }))
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('agent_id')
                    ->label('Agent (Optional)')
                    ->options(User::where('role', 'agent')->get()->mapWithKeys(function ($user) {
                        return [$user->id => $user->first_name . ' ' . $user->last_name];
                    }))
                    ->searchable()
                    ->preload(),
                TextInput::make('rent_amount')
                    ->label('Monthly Rent Amount')
                    ->required()
                    ->numeric()
                    ->prefix('₦'),
                TextInput::make('shelterbaze_commission')
                    ->label('Shelterbaze Commission')
                    ->required()
                    ->numeric()
                    ->prefix('₦'),
                TextInput::make('total_amount')
                    ->label('Total Amount')
                    ->required()
                    ->numeric()
                    ->prefix('₦'),
                DatePicker::make('agreement_start_date')
                    ->label('Agreement Start Date')
                    ->required(),
                DatePicker::make('agreement_end_date')
                    ->label('Agreement End Date')
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
                    ->label('Terms & Conditions')
                    ->placeholder('Enter specific terms and conditions for this rental agreement...')
                    ->rows(6)
                    ->columnSpanFull(),
            ]);
    }
}
