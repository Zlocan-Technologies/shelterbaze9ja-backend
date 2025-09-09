<?php

namespace App\Filament\Resources\RentPayments\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RentPaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('rental_agreement_id')
                    ->numeric(),
                TextEntry::make('user_id')
                    ->numeric(),
                TextEntry::make('amount')
                    ->numeric(),
                TextEntry::make('payment_type'),
                TextEntry::make('bank_account_number'),
                TextEntry::make('bank_name'),
                TextEntry::make('account_name'),
                TextEntry::make('payment_proof_url'),
                TextEntry::make('payment_date')
                    ->date(),
                TextEntry::make('due_date')
                    ->date(),
                TextEntry::make('next_due_date')
                    ->date(),
                TextEntry::make('status'),
                TextEntry::make('verified_by')
                    ->numeric(),
                TextEntry::make('verified_at')
                    ->dateTime(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
