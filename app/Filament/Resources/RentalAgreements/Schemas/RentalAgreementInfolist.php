<?php

namespace App\Filament\Resources\RentalAgreements\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RentalAgreementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('property_id')
                    ->numeric(),
                TextEntry::make('tenant_id')
                    ->numeric(),
                TextEntry::make('landlord_id')
                    ->numeric(),
                TextEntry::make('agent_id')
                    ->numeric(),
                TextEntry::make('rent_amount')
                    ->numeric(),
                TextEntry::make('shelterbaze_commission')
                    ->numeric(),
                TextEntry::make('total_amount')
                    ->numeric(),
                TextEntry::make('agreement_start_date')
                    ->date(),
                TextEntry::make('agreement_end_date')
                    ->date(),
                TextEntry::make('status'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
