<?php

namespace App\Filament\Resources\RentSavings\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class RentSavingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user')
                    ->label('User')
                    ->getStateUsing(fn($record) => $record->user ? $record->user->name : 'N/A')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('property')
                    ->label('Property')
                    ->getStateUsing(fn($record) => $record->property ? $record->property->title : ($record->is_external_property ? 'External Property' : 'N/A'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('plan_name')
                    ->searchable(),
                TextColumn::make('target_amount')
                    ->numeric()
                    ->prefix('₦')
                    ->sortable(),
                TextColumn::make('current_amount')
                    ->numeric()
                    ->prefix('₦')
                    ->sortable(),
                TextColumn::make('progress')
                    ->label('Progress')
                    ->getStateUsing(fn($record) => $record->target_amount > 0 ? round(($record->current_amount / $record->target_amount) * 100, 1) . '%' : '0%')
                    ->sortable(),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'completed' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('early_withdrawal_penalty')
                    ->label('Withdrawal Penalty')
                    ->numeric()
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('deposit_charge')
                    ->label('Deposit Charge')
                    ->numeric()
                    ->suffix('%')
                    ->sortable(),
                IconColumn::make('is_external_property')
                    ->label('External')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('is_external_property')
                    ->label('Property Type')
                    ->options([
                        1 => 'External Property',
                        0 => 'Platform Property',
                    ]),
                // TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                // EditAction::make(),
                Action::make('view_transactions')
                    ->label('View Transactions')
                    ->icon('heroicon-o-banknotes')
                    ->color('info')
                    ->modalHeading(fn($record) => 'Transactions for "' . $record->plan_name . '"')
                    ->modalSubheading(fn($record) => 'Total Transactions: ' . $record->transactions()->count())
                    ->modalWidth('5xl')
                    ->form([
                        Section::make('Savings Summary')
                            ->schema([
                                TextInput::make('target_amount')
                                    ->label('Target Amount')
                                    ->default(fn($record) => '₦' . number_format($record->target_amount, 2))
                                    ->disabled(),
                                TextInput::make('current_amount')
                                    ->label('Current Amount')
                                    ->default(fn($record) => '₦' . number_format($record->current_amount, 2))
                                    ->disabled(),
                                TextInput::make('progress')
                                    ->label('Progress')
                                    ->default(fn($record) => $record->target_amount > 0 ? round(($record->current_amount / $record->target_amount) * 100, 1) . '%' : '0%')
                                    ->disabled(),
                            ])->columns(3),

                        Section::make('Transaction History')
                            ->schema([
                                Repeater::make('transaction_list')
                                    ->label('')
                                    ->schema([
                                        TextInput::make('transaction_type')
                                            ->label('Type')
                                            ->disabled()
                                            ->columnSpan(1),
                                        TextInput::make('status')
                                            ->label('Status')
                                            ->disabled()
                                            ->columnSpan(1),
                                        TextInput::make('amount')
                                            ->label('Amount')
                                            ->disabled()
                                            ->columnSpan(1),
                                        TextInput::make('charge_amount')
                                            ->label('Charges')
                                            ->disabled()
                                            ->columnSpan(1),
                                        TextInput::make('penalty_amount')
                                            ->label('Penalty')
                                            ->disabled()
                                            ->columnSpan(1),
                                        TextInput::make('net_amount')
                                            ->label('Net Amount')
                                            ->disabled()
                                            ->columnSpan(1),
                                        TextInput::make('payment_method')
                                            ->label('Payment Method')
                                            ->disabled()
                                            ->columnSpan(1),
                                        TextInput::make('payment_reference')
                                            ->label('Reference')
                                            ->disabled()
                                            ->columnSpan(2),
                                        TextInput::make('is_early_withdrawal')
                                            ->label('Early Withdrawal')
                                            ->disabled()
                                            ->columnSpan(1),
                                        TextInput::make('created_at')
                                            ->label('Date')
                                            ->disabled()
                                            ->columnSpan(2),
                                        TextInput::make('notes')
                                            ->label('Notes')
                                            ->disabled()
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(3)
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->default(fn($record) => $record->transactions()
                                        ->orderBy('created_at', 'desc')
                                        ->get()
                                        ->map(function ($transaction) {
                                            return [
                                                'transaction_type' => ucfirst($transaction->transaction_type),
                                                'status' => ucfirst($transaction->status),
                                                'amount' => '₦' . number_format($transaction->amount, 2),
                                                'charge_amount' => $transaction->charge_amount > 0 ? '₦' . number_format($transaction->charge_amount, 2) : '₦0.00',
                                                'penalty_amount' => $transaction->penalty_amount > 0 ? '₦' . number_format($transaction->penalty_amount, 2) : '₦0.00',
                                                'net_amount' => '₦' . number_format($transaction->net_amount, 2),
                                                'payment_method' => $transaction->payment_method ? ucfirst(str_replace('_', ' ', $transaction->payment_method)) : 'N/A',
                                                'payment_reference' => $transaction->payment_reference ?: 'N/A',
                                                'is_early_withdrawal' => $transaction->is_early_withdrawal ? 'Yes' : 'No',
                                                'created_at' => $transaction->created_at->format('M d, Y H:i'),
                                                'notes' => $transaction->notes ?: 'N/A',
                                            ];
                                        })
                                        ->toArray())
                                    ->itemLabel(fn(array $state): ?string => 
                                        ($state['transaction_type'] ?? 'Transaction') . ' - ' . ($state['amount'] ?? '₦0.00') . ' (' . ($state['status'] ?? 'Unknown') . ')'
                                    ),
                            ]),
                    ])
                    ->action(function ($record, $data) {
                        // No action needed, just viewing data
                    })
                    ->visible(fn($record) => $record->transactions()->count() > 0),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DeleteBulkAction::make(),
                    // ForceDeleteBulkAction::make(),
                    // RestoreBulkAction::make(),
                ]),
            ]);
    }
}
