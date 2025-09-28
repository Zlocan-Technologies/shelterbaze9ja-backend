<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AuditLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('User')
                    ->options(function () {
                        return \App\Models\User::all()->mapWithKeys(function ($user) {
                            return [$user->id => $user->first_name . ' ' . $user->last_name];
                        })->toArray();
                    })
                    ->searchable()
                    ->preload(),
                TextInput::make('action')
                    ->label('Action')
                    ->required()
                    ->placeholder('e.g., created, updated, deleted'),
                TextInput::make('model_type')
                    ->label('Model Type')
                    ->placeholder('e.g., App\Models\User'),
                TextInput::make('model_id')
                    ->label('Model ID')
                    ->numeric(),
                KeyValue::make('old_values')
                    ->label('Old Values')
                    ->keyLabel('Field')
                    ->valueLabel('Old Value'),
                KeyValue::make('new_values')
                    ->label('New Values')
                    ->keyLabel('Field')
                    ->valueLabel('New Value'),
                TextInput::make('ip_address')
                    ->label('IP Address'),
                Textarea::make('user_agent')
                    ->label('User Agent')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
