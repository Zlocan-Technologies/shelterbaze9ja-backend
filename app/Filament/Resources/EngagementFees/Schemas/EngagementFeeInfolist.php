<?php

namespace App\Filament\Resources\EngagementFees\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class EngagementFeeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $record = $schema->getRecord();
        return $schema
            ->components([
                TextEntry::make('user_id')
                    ->formatStateUsing(function () use($record) {
                        return $record->user?->name ?? 'N/A';
                    })
                    ->label('Customer'),
                    
                TextEntry::make('property_id')
                    ->formatStateUsing(function () use($record) {
                        return $record->property?->title ?? 'N/A';
                    })
                    ->label('Property'),
                TextEntry::make('amount')
                    ->prefix('₦')
                    ->numeric(),
                TextEntry::make('payment_reference'),
                TextEntry::make('payment_status'),
                TextEntry::make('payment_method'),
                TextEntry::make('paid_at')
                    ->dateTime(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
