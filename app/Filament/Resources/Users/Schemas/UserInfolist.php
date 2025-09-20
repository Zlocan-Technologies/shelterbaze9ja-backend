<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Basic Information
                TextEntry::make('first_name'),
                TextEntry::make('last_name'),
                TextEntry::make('email')
                    ->copyable(),
                TextEntry::make('phone_number')
                    ->copyable(),
                TextEntry::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'agent' => 'warning',
                        'landlord' => 'success',
                        'user' => 'info',
                        default => 'gray',
                    }),
                
                // Account Status
                TextEntry::make('email_verified_at')
                    ->dateTime()
                    ->placeholder('Not verified'),
                TextEntry::make('phone_verified_at')
                    ->dateTime()
                    ->placeholder('Not verified'),
                IconEntry::make('profile_completed')
                    ->boolean(),
                TextEntry::make('account_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'suspended' => 'danger',
                        'declined' => 'gray',
                        default => 'gray',
                    }),

                // Profile Information
                TextEntry::make('profile.nin_number')
                    ->placeholder('Not provided'),
                TextEntry::make('profile.agent_id')
                    ->placeholder('N/A for non-agents'),
                TextEntry::make('profile.address')
                    ->placeholder('Not provided'),
                TextEntry::make('profile.state')
                    ->placeholder('Not provided'),
                TextEntry::make('profile.lga')
                    ->placeholder('Not provided'),

                // Verification Documents
                ImageEntry::make('profile.nin_selfie_url')
                    ->height(200)
                    ->width(300),
                ImageEntry::make('profile.id_card_url')
                    ->height(200)
                    ->width(300),
                
                TextEntry::make('profile.verification_documents')
                    ->placeholder('No additional documents')
                    ->formatStateUsing(function ($state) {
                        if (is_array($state) && !empty($state)) {
                            return collect($state)
                                ->map(fn ($value, $key) => "{$key}: {$value}")
                                ->join(', ');
                        }
                        return 'No additional documents';
                    }),

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
