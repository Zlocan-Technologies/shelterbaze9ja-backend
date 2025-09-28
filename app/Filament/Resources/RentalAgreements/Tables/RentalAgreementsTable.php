<?php

namespace App\Filament\Resources\RentalAgreements\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class RentalAgreementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('property')
                    ->label('Property')
                    ->getStateUsing(fn($record) => $record->property ? $record->property->title : 'N/A')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tenant')
                    ->label('Tenant')
                    ->getStateUsing(fn($record) => $record->tenant ? $record->tenant->name : 'N/A')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('landlord')
                    ->label('Landlord')
                    ->getStateUsing(fn($record) => $record->landlord ? $record->landlord->name : 'N/A')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('agent')
                    ->label('Agent')
                    ->getStateUsing(fn($record) => $record->agent ? $record->agent->name : 'No Agent')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('rent_amount')
                    ->label('Rent Amount')
                    ->numeric()
                    ->prefix('₦')
                    ->sortable(),
                TextColumn::make('shelterbaze_commission')
                    ->label('Commission')
                    ->numeric()
                    ->prefix('₦')
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->numeric()
                    ->prefix('₦')
                    ->sortable(),
                TextColumn::make('duration')
                    ->label('Duration')
                    ->getStateUsing(function ($record) {
                        if ($record->agreement_start_date && $record->agreement_end_date) {
                            $start = $record->agreement_start_date;
                            $end = $record->agreement_end_date;
                            $months = $start->diffInMonths($end);
                            return $months . ' month' . ($months !== 1 ? 's' : '');
                        }
                        return 'N/A';
                    })
                    ->sortable(),
                TextColumn::make('agreement_start_date')
                    ->label('Start Date')
                    ->date('M d, Y')
                    ->sortable(),
                TextColumn::make('agreement_end_date')
                    ->label('End Date')
                    ->date('M d, Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'expired' => 'danger',
                        'terminated' => 'gray',
                        default => 'secondary',
                    }),
                IconColumn::make('has_rent_payments')
                    ->label('Payments')
                    ->getStateUsing(fn($record) => $record->rentPayments()->count() > 0)
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
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'expired' => 'Expired',
                        'terminated' => 'Terminated',
                    ]),
                SelectFilter::make('has_agent')
                    ->label('Agent Status')
                    ->options([
                        'with_agent' => 'With Agent',
                        'without_agent' => 'Without Agent',
                    ])
                    ->query(function ($query, $data) {
                        if ($data['value'] === 'with_agent') {
                            return $query->whereNotNull('agent_id');
                        } elseif ($data['value'] === 'without_agent') {
                            return $query->whereNull('agent_id');
                        }
                        return $query;
                    }),
                // TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                // EditAction::make(),
                Action::make('view_rent_payments')
                    ->label('View Rent Payments')
                    ->icon('heroicon-o-credit-card')
                    ->color('info')
                    ->modalHeading(fn($record) => 'Rent Payments for Agreement #' . $record->id)
                    ->modalSubheading(fn($record) => 'Total Payments: ' . $record->rentPayments()->count())
                    ->modalWidth('5xl')
                    ->form([
                        Section::make('Agreement Summary')
                            ->schema([
                                TextInput::make('property_title')
                                    ->label('Property')
                                    ->default(fn($record) => $record->property ? $record->property->title : 'N/A')
                                    ->disabled(),
                                TextInput::make('tenant_name')
                                    ->label('Tenant')
                                    ->default(fn($record) => $record->tenant ? $record->tenant->name : 'N/A')
                                    ->disabled(),
                                TextInput::make('rent_amount_formatted')
                                    ->label('Monthly Rent')
                                    ->default(fn($record) => '₦' . number_format($record->rent_amount, 2))
                                    ->disabled(),
                                TextInput::make('agreement_period')
                                    ->label('Agreement Period')
                                    ->default(function ($record) {
                                        if ($record->agreement_start_date && $record->agreement_end_date) {
                                            return $record->agreement_start_date->format('M d, Y') . ' - ' . $record->agreement_end_date->format('M d, Y');
                                        }
                                        return 'N/A';
                                    })
                                    ->disabled(),
                            ])->columns(2),

                        Section::make('Rent Payments History')
                            ->schema([
                                Repeater::make('rent_payments_list')
                                    ->label('')
                                    ->schema([
                                        TextInput::make('payment_date')
                                            ->label('Payment Date')
                                            ->disabled()
                                            ->columnSpan(1),
                                        TextInput::make('amount')
                                            ->label('Amount')
                                            ->disabled()
                                            ->columnSpan(1),
                                        TextInput::make('payment_status')
                                            ->label('Status')
                                            ->disabled()
                                            ->columnSpan(1),
                                        TextInput::make('payment_method')
                                            ->label('Payment Type')
                                            ->disabled()
                                            ->columnSpan(1),
                                        TextInput::make('payment_reference')
                                            ->label('Reference ID')
                                            ->disabled()
                                            ->columnSpan(1),
                                        TextInput::make('payment_month')
                                            ->label('Payment Period')
                                            ->disabled()
                                            ->columnSpan(1),
                                        TextInput::make('notes')
                                            ->label('Admin Notes')
                                            ->disabled()
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(3)
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->default(fn($record) => $record->rentPayments()
                                        ->orderBy('payment_date', 'desc')
                                        ->get()
                                        ->map(function ($payment) {
                                            return [
                                                'payment_month' => $payment->payment_date ? $payment->payment_date->format('F Y') : 'N/A',
                                                'amount' => '₦' . number_format($payment->amount, 2),
                                                'payment_status' => ucfirst($payment->status),
                                                'payment_method' => $payment->payment_type ? ucfirst(str_replace('_', ' ', $payment->payment_type)) : 'N/A',
                                                'payment_reference' => $payment->id ? 'REF-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT) : 'N/A',
                                                'payment_date' => $payment->payment_date ? $payment->payment_date->format('M d, Y') : 'N/A',
                                                'notes' => $payment->admin_notes ?: 'N/A',
                                            ];
                                        })
                                        ->toArray())
                                    ->itemLabel(fn(array $state): ?string => 
                                        ($state['payment_date'] ?? 'Payment') . ' - ' . ($state['amount'] ?? '₦0.00') . ' (' . ($state['payment_status'] ?? 'Unknown') . ')'
                                    ),
                            ]),
                    ])
                    ->action(function ($record, $data) {
                        // No action needed, just viewing data
                    })
                    ->visible(fn($record) => $record->rentPayments()->count() > 0),
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
