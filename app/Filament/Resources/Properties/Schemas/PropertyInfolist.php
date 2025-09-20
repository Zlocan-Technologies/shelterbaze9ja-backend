<?php

namespace App\Filament\Resources\Properties\Schemas;

use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PropertyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $record = $schema->getRecord();
        return $schema
            ->components([
                // Basic Property Information
                TextEntry::make('landlord_id')
                    ->formatStateUsing(function () use ($record) {
                        return $record->landlord->name ?? '';
                    }),
                TextEntry::make('agent_id')
                    ->formatStateUsing(function () use ($record) {
                        return $record->agent->name ?? 'Not assigned';
                    }),
                TextEntry::make('title'),
                TextEntry::make('property_type'),
                TextEntry::make('rent_amount')
                    ->numeric()
                    ->prefix('₦'),
                TextEntry::make('shelterbaze_commission')
                    ->numeric()
                    ->prefix('₦'),
                TextEntry::make('total_amount')
                    ->numeric()
                    ->prefix('₦'),
                TextEntry::make('state'),
                TextEntry::make('lga'),
                TextEntry::make('longitude')
                    ->numeric(),
                TextEntry::make('latitude')
                    ->numeric(),
                TextEntry::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'success',
                        'closed' => 'warning',
                        'rented' => 'info',
                        default => 'gray',
                    }),
                TextEntry::make('verification_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'verified' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextEntry::make('verified_by')
                    ->formatStateUsing(function () use ($record) {
                        if ($record->verified_by) {
                            $verifier = User::find($record->verified_by);
                            return $verifier ? $verifier->name : 'Unknown User';
                        }
                        return 'Not verified';
                    }),
                TextEntry::make('verified_at')
                    ->dateTime(),

                // Timestamps
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->placeholder('Not deleted'),
            ]);
    }
}
