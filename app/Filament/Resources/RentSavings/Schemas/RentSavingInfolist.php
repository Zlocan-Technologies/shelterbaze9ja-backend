<?php

namespace App\Filament\Resources\RentSavings\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RentSavingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user_id')
                    ->numeric(),
                TextEntry::make('property_id')
                    ->numeric(),
                TextEntry::make('plan_name'),
                TextEntry::make('target_amount')
                    ->numeric(),
                TextEntry::make('current_amount')
                    ->numeric(),
                TextEntry::make('due_date')
                    ->date(),
                TextEntry::make('status'),
                TextEntry::make('early_withdrawal_penalty')
                    ->numeric(),
                TextEntry::make('deposit_charge')
                    ->numeric(),
                IconEntry::make('is_external_property')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
