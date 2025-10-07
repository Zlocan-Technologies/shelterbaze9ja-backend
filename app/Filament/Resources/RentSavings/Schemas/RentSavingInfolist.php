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
                TextEntry::make('user.name')
                    ->placeholder('N/A'),
                TextEntry::make('property.title')
                    ->placeholder('External Property'),
                TextEntry::make('plan_name'),
                TextEntry::make('target_amount')
                    ->formatStateUsing(fn($state) => '₦' . number_format($state, 2)),
                TextEntry::make('current_amount')
                    ->formatStateUsing(fn($state) => '₦' . number_format($state, 2)),
                TextEntry::make('progress_percentage')
                    ->formatStateUsing(fn($state, $record) => $record->target_amount > 0 ? round(($record->current_amount / $record->target_amount) * 100, 1) . '%' : '0%'),
                TextEntry::make('due_date')
                    ->date(),
                TextEntry::make('status')
                    ->formatStateUsing(fn($state) => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'completed' => 'info', 
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextEntry::make('early_withdrawal_penalty')
                    ->formatStateUsing(fn($state) => '₦' . number_format($state, 2)),
                TextEntry::make('deposit_charge')
                    ->formatStateUsing(fn($state) => '₦' . number_format($state, 2)),
                IconEntry::make('is_external_property')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
