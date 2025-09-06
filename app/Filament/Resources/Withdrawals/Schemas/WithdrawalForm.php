<?php

namespace App\Filament\Resources\Withdrawals\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class WithdrawalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('bank_name')
                    ->required(),
                TextInput::make('account_name')
                    ->required(),
                TextInput::make('account_number')
                    ->required(),
                Textarea::make('reason_for_rejection')
                    ->columnSpanFull(),
                Select::make('status')
                    ->options([
            'PENDING' => 'P e n d i n g',
            'PROCESSING' => 'P r o c e s s i n g',
            'PAID' => 'P a i d',
            'REJECTED' => 'R e j e c t e d',
        ])
                    ->default('PENDING')
                    ->required(),
            ]);
    }
}
