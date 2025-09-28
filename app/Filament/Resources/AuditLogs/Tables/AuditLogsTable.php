<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user')
                    ->label('User')
                    ->getStateUsing(fn($record) => $record->user ? $record->user->first_name . ' ' . $record->user->last_name : 'System')
                    ->placeholder('System')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('action')
                    ->badge()
                    ->formatStateUsing(fn($state) => str_replace('_', ' ', ucfirst($state)))
                    ->color(fn(string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        'logged_in' => 'primary',
                        'logged_out' => 'secondary',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('model_type')
                    ->label('Model')
                    ->formatStateUsing(fn($state) => $state ? class_basename($state) : 'N/A')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('model_id')
                    ->label('Record ID')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('has_changes')
                    ->label('Has Changes')
                    ->getStateUsing(fn($record) => !empty($record->old_values) || !empty($record->new_values))
                    ->boolean()
                    ->trueIcon('heroicon-o-document-text')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('info')
                    ->falseColor('gray'),
                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('user_agent')
                    ->label('User Agent')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime('M d, Y H:i:s')
                    ->sortable()
                    ->description(fn($record) => $record->created_at->diffForHumans()),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                        'logged_in' => 'Logged In',
                        'logged_out' => 'Logged Out',
                    ]),
                SelectFilter::make('model_type')
                    ->options(function () {
                        return \App\Models\AuditLog::distinct()
                            ->whereNotNull('model_type')
                            ->pluck('model_type', 'model_type')
                            ->map(fn($value) => class_basename($value))
                            ->toArray();
                    }),
                SelectFilter::make('user_id')
                    ->options(function () {
                        return \App\Models\User::all()->mapWithKeys(function ($user) {
                            return [$user->id => $user->first_name . ' ' . $user->last_name];
                        })->toArray();
                    })
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('view_changes')
                    ->label('View Changes')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn($record) => 'Audit Log Details - ' . ucfirst($record->action))
                    ->modalWidth('5xl')
                    ->form([
                        Section::make('Basic Information')
                            ->schema([
                                TextInput::make('user_name')
                                    ->label('User')
                                    ->default(fn($record) => $record->user ? $record->user->first_name . ' ' . $record->user->last_name : 'System')
                                    ->disabled(),
                                TextInput::make('action_formatted')
                                    ->label('Action')
                                    ->default(fn($record) => str_replace('_', ' ', ucfirst($record->action)))
                                    ->disabled(),
                                TextInput::make('model_info')
                                    ->label('Model')
                                    ->default(fn($record) => $record->model_type ? class_basename($record->model_type) . ' #' . $record->model_id : 'N/A')
                                    ->disabled(),
                                TextInput::make('timestamp')
                                    ->label('Date & Time')
                                    ->default(fn($record) => $record->created_at->format('M d, Y H:i:s') . ' (' . $record->created_at->diffForHumans() . ')')
                                    ->disabled(),
                            ])->columns(2),

                        Section::make('System Information')
                            ->schema([
                                TextInput::make('ip_address')
                                    ->label('IP Address')
                                    ->default(fn($record) => $record->ip_address ?: 'N/A')
                                    ->disabled(),
                                TextInput::make('user_agent')
                                    ->label('User Agent')
                                    ->default(fn($record) => $record->user_agent ?: 'N/A')
                                    ->disabled(),
                            ])->columns(1),

                        Section::make('Data Changes')
                            ->schema([
                                KeyValue::make('old_values')
                                    ->label('Old Values')
                                    ->disabled()
                                    ->keyLabel('Field')
                                    ->valueLabel('Old Value'),
                                KeyValue::make('new_values')
                                    ->label('New Values')
                                    ->disabled()
                                    ->keyLabel('Field')
                                    ->valueLabel('New Value'),
                            ])
                            ->visible(fn($record) => !empty($record->old_values) || !empty($record->new_values)),
                    ])
                    ->action(function ($record, $data) {
                        // No action needed, just viewing data
                    })
                    ->visible(fn($record) => !empty($record->old_values) || !empty($record->new_values)),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                // Remove bulk actions as audit logs should not be deleted
            ]);
    }
}
