<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name')
                    ->required(),
                TextInput::make('last_name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('phone_number')
                    ->tel()
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->required(),
                Select::make('role')
                    ->options(['user' => 'User', 'landlord' => 'Landlord', 'agent' => 'Agent', 'admin' => 'Admin'])
                    ->default('user')
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                DateTimePicker::make('phone_verified_at'),
                Toggle::make('profile_completed')
                    ->required(),
                Select::make('account_status')
                    ->options([
            'pending' => 'Pending',
            'active' => 'Active',
            'declined' => 'Declined',
            'suspended' => 'Suspended',
        ])
                    ->default('pending')
                    ->required(),
            ]);
    }
}
