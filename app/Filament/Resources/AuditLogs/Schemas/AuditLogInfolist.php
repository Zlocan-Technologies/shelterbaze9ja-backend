<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user_id')
                    ->formatStateUsing(fn($state, $record) => $record->user ? $record->user->first_name . ' ' . $record->user->last_name : 'System User'),
                TextEntry::make('action')
                    ->formatStateUsing(fn($state) => str_replace('_', ' ', ucfirst($state))),
                TextEntry::make('model_type')
                    ->formatStateUsing(fn($state) => $state ? class_basename($state) : 'N/A'),
                TextEntry::make('model_id')
                    ->formatStateUsing(fn($state) => $state ? '#' . $state : 'N/A'),
                TextEntry::make('ip_address')
                    ->placeholder('Unknown IP'),
                TextEntry::make('user_agent')
                    ->placeholder('Unknown User Agent'),
                TextEntry::make('old_values')
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) return 'No previous values';
                        return json_encode($state, JSON_PRETTY_PRINT);
                    }),
                TextEntry::make('new_values')
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) return 'No new values';
                        return json_encode($state, JSON_PRETTY_PRINT);
                    }),
                TextEntry::make('created_at')
                    ->dateTime('F j, Y \a\t g:i A')
                    ->formatStateUsing(fn($state) => $state->format('F j, Y \a\t g:i A') . ' (' . $state->diffForHumans() . ')'),
                TextEntry::make('updated_at')
                    ->dateTime('F j, Y \a\t g:i A'),
            ]);
    }
}
