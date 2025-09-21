<?php

namespace App\Filament\Resources\EngagementFees\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class EngagementFeesTable
{
    
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user_id')
                    ->label('Customer')
                    ->getStateUsing(fn($record) => $record->user?->name ?? 'N/A')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('property_id')
                    ->label('Property')
                    ->getStateUsing(fn($record) => $record->property?->title ?? 'N/A')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('landlord')
                    ->label('Landlord')
                    ->getStateUsing(fn($record) => $record->property?->landlord?->name ?? 'N/A')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('amount')
                    ->prefix('₦')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('payment_reference')
                    ->searchable(),
               
                    //add badge statuses for ['pending', 'completed', 'failed']
                    TextColumn::make('payment_status')
                        ->label('Payment Status')
                        ->getStateUsing(fn($record) => $record->payment_status)
                        ->badge()
                        ->colors([
                            'warning' => 'pending',
                            'success' => 'completed',
                            'danger' => 'failed',
                        ]),
                TextColumn::make('payment_method')
                    ->searchable(),
                TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable(),
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
                // TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                // EditAction::make(),
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
