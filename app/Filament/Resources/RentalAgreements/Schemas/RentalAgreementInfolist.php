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
                TextEntry::make('property.title')
                    ->placeholder('No property assigned'),
                TextEntry::make('tenant.name')
                    ->placeholder('No tenant assigned'),
                TextEntry::make('landlord.name')
                    ->placeholder('No landlord assigned'),
                TextEntry::make('agent.name')
                    ->placeholder('No agent assigned'),
                TextEntry::make('rent_amount')
                    ->formatStateUsing(fn($state) => '₦' . number_format($state, 2)),
                TextEntry::make('shelterbaze_commission')
                    ->formatStateUsing(fn($state) => '₦' . number_format($state, 2)),
                TextEntry::make('total_amount')
                    ->formatStateUsing(fn($state) => '₦' . number_format($state, 2)),
                TextEntry::make('commission_percentage')
                    ->formatStateUsing(fn($state, $record) => $record->rent_amount > 0 ? round(($record->shelterbaze_commission / $record->rent_amount) * 100, 2) . '%' : '0%'),
                TextEntry::make('agreement_start_date')
                    ->date('F j, Y'),
                TextEntry::make('agreement_end_date')
                    ->date('F j, Y'),
                TextEntry::make('status')
                    ->formatStateUsing(fn($state) => ucfirst($state))
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'expired' => 'danger',
                        'terminated' => 'gray',
                        default => 'secondary',
                    }),
                TextEntry::make('terms_conditions')
                    ->placeholder('No terms and conditions specified'),
                TextEntry::make('created_at')
                    ->dateTime('F j, Y \a\t g:i A'),
                TextEntry::make('updated_at')
                    ->dateTime('F j, Y \a\t g:i A'),
            ]);
    }
}
