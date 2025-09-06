<?php

namespace App\Filament\Resources\Properties\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PropertyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('landlord_id')
                    ->numeric(),
                TextEntry::make('agent_id')
                    ->numeric(),
                TextEntry::make('title'),
                TextEntry::make('property_type'),
                TextEntry::make('rent_amount')
                    ->numeric(),
                TextEntry::make('shelterbaze_commission')
                    ->numeric(),
                TextEntry::make('total_amount')
                    ->numeric(),
                TextEntry::make('state'),
                TextEntry::make('lga'),
                TextEntry::make('longitude')
                    ->numeric(),
                TextEntry::make('latitude')
                    ->numeric(),
                TextEntry::make('status'),
                TextEntry::make('verification_status'),
                TextEntry::make('verified_by')
                    ->numeric(),
                TextEntry::make('verified_at')
                    ->dateTime(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('deleted_at')
                    ->dateTime(),
            ]);
    }
}
