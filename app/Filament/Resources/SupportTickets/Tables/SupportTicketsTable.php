<?php

namespace App\Filament\Resources\SupportTickets\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SupportTicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ticket_number')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Ticket number copied')
                    ->weight('bold'),
                TextColumn::make('user')
                    ->label('Customer')
                    ->getStateUsing(fn($record) => $record->user ? $record->user->first_name . ' ' . $record->user->last_name : 'N/A')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    }),
                TextColumn::make('ticket_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn($state) => ucwords(str_replace('_', ' ', $state)))
                    ->color(fn(string $state): string => match ($state) {
                        'general' => 'gray',
                        'property_issue' => 'warning',
                        'payment_issue' => 'danger',
                        'technical' => 'info',
                        'account_issue' => 'primary',
                        default => 'secondary',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn($state) => ucwords(str_replace('_', ' ', $state)))
                    ->color(fn(string $state): string => match ($state) {
                        'open' => 'danger',
                        'in_progress' => 'warning',
                        'resolved' => 'success',
                        'closed' => 'gray',
                        default => 'secondary',
                    }),
                TextColumn::make('priority')
                    ->badge()
                    ->formatStateUsing(fn($state) => ucfirst($state))
                    ->color(fn(string $state): string => match ($state) {
                        'low' => 'success',
                        'medium' => 'warning',
                        'high' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('assignedTo')
                    ->label('Assigned To')
                    ->getStateUsing(fn($record) => $record->assignedTo ? $record->assignedTo->first_name . ' ' . $record->assignedTo->last_name : 'Unassigned')
                    ->placeholder('Unassigned')
                    ->color(fn($record) => $record->assignedTo ? 'success' : 'danger'),
                IconColumn::make('has_attachments')
                    ->label('Attachments')
                    ->getStateUsing(fn($record) => !empty($record->attachments))
                    ->boolean()
                    ->trueIcon('heroicon-o-paper-clip')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('info')
                    ->falseColor('gray'),
                TextColumn::make('property.title')
                    ->label('Property')
                    ->placeholder('No property')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('age')
                    ->label('Age')
                    ->getStateUsing(fn($record) => $record->created_at->diffForHumans())
                    ->sortable(query: function ($query, $direction) {
                        return $query->orderBy('created_at', $direction === 'asc' ? 'desc' : 'asc');
                    }),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('resolved_at')
                    ->label('Resolved')
                    ->dateTime('M d, Y H:i')
                    ->placeholder('Not resolved')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'in_progress' => 'In Progress',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed',
                    ])
                    ->multiple(),
                SelectFilter::make('priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                    ])
                    ->multiple(),
                SelectFilter::make('ticket_type')
                    ->label('Type')
                    ->options([
                        'general' => 'General',
                        'property_issue' => 'Property Issue',
                        'payment_issue' => 'Payment Issue',
                        'technical' => 'Technical',
                        'account_issue' => 'Account Issue',
                    ])
                    ->multiple(),
                SelectFilter::make('assigned_to')
                    ->label('Assigned To')
                    ->options(function () {
                        return User::where('role', 'admin')->get()->mapWithKeys(function ($user) {
                            return [$user->id => $user->first_name . ' ' . $user->last_name];
                        })->toArray();
                    })
                    ->searchable()
                    ->preload(),
                SelectFilter::make('unassigned')
                    ->label('Assignment Status')
                    ->options([
                        'unassigned' => 'Unassigned',
                        'assigned' => 'Assigned',
                    ])
                    ->query(function ($query, $data) {
                        if ($data['value'] === 'unassigned') {
                            return $query->whereNull('assigned_to');
                        } elseif ($data['value'] === 'assigned') {
                            return $query->whereNotNull('assigned_to');
                        }
                        return $query;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('assign')
                    ->label('Assign')
                    ->icon('heroicon-o-user-plus')
                    ->color('info')
                    ->form([
                        Select::make('assigned_to')
                            ->label('Assign to')
                            ->options(function () {
                                return User::where('role', 'admin')->get()->mapWithKeys(function ($user) {
                                    return [$user->id => $user->first_name . ' ' . $user->last_name];
                                })->toArray();
                            })
                            ->required()
                            ->searchable()
                            ->preload(),
                    ])
                    ->action(function ($record, $data) {
                        $record->assignTo($data['assigned_to']);
                    })
                    ->visible(fn($record) => $record->status !== 'closed' && $record->status !== 'resolved'),
                Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([
                        Textarea::make('resolution_notes')
                            ->label('Resolution Notes')
                            ->required()
                            ->rows(4)
                            ->placeholder('Describe how this ticket was resolved...'),
                    ])
                    ->action(function ($record, $data) {
                        $record->resolve($data['resolution_notes']);
                    })
                    ->visible(fn($record) => in_array($record->status, ['open', 'in_progress'])),
                Action::make('close')
                    ->label('Close')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->close();
                    })
                    ->visible(fn($record) => $record->status === 'resolved'),
                Action::make('reopen')
                    ->label('Reopen')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->reopen();
                    })
                    ->visible(fn($record) => in_array($record->status, ['resolved', 'closed'])),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
