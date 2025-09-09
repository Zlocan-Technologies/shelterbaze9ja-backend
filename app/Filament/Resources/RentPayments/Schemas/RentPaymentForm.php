<?php

namespace App\Filament\Resources\RentPayments\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class RentPaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('rental_agreement_id')
                    ->required()
                    ->numeric(),
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                Select::make('payment_type')
                    ->options(['online' => 'Online', 'offline' => 'Offline'])
                    ->required(),
                TextInput::make('bank_account_number'),
                TextInput::make('bank_name'),
                TextInput::make('account_name'),
                TextInput::make('payment_proof_url')
                    ->required(),
                DatePicker::make('payment_date')
                    ->required(),
                DatePicker::make('due_date')
                    ->required(),
                DatePicker::make('next_due_date'),
                Select::make('status')
                    ->options(['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected'])
                    ->default('pending')
                    ->required(),
                TextInput::make('verified_by')
                    ->numeric(),
                DateTimePicker::make('verified_at'),
                Textarea::make('rejection_reason')
                    ->columnSpanFull(),
                Textarea::make('admin_notes')
                    ->columnSpanFull(),
            ]);
    }
}
