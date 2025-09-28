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
                TextEntry::make('landlord')
                    ->label('Landlord')
                    ->getStateUsing(fn($record) => $record->rentalAgreement->landlord->name ?? 'N/A'),
                TextEntry::make('property')
                    ->label('Property')
                    ->getStateUsing(fn($record) => $record->rentalAgreement->property->title ?? 'N/A'),
                TextEntry::make('user.name')
                    ->label('Tenant'),
                TextEntry::make('amount')
                    ->numeric(),
                TextEntry::make('payment_type'),
                TextEntry::make('bank_account_number'),
                TextEntry::make('bank_name'),
                TextEntry::make('account_name'),
                TextEntry::make('payment_proof_url')
                    ->label('Payment Proof')
                    ->url(fn($record) => $record->payment_proof_url)
                    ->openUrlInNewTab()
                    ->placeholder('No proof uploaded'),
                TextEntry::make('payment_date')
                    ->date(),
                TextEntry::make('due_date')
                    ->date(),
                TextEntry::make('next_due_date')
                    ->date(),
                TextEntry::make('status'),
                TextEntry::make('verified_by_name')
                    ->label('Verified By')
                    ->getStateUsing(fn($record) => $record->verifiedBy->name ?? 'Not verified'),
                TextEntry::make('verified_at')
                    ->dateTime(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
