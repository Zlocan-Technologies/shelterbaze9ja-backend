<?php

namespace App\Filament\Resources\Withdrawals\Tables;

use App\Enums\WithdrawalStatus;
use App\Services\NotificationService;
use App\Services\Wallet\WalletService;
use App\Traits\SendMail;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class WithdrawalsTable
{
    use SendMail;
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user_id')
                    ->label('User')
                    ->getStateUsing(fn($record) => $record->user ? $record->user->name : 'N/A')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')
                    ->numeric()
                    ->prefix('₦')
                    ->sortable(),
                TextColumn::make('bank_name')
                    ->searchable(),
                TextColumn::make('account_name')
                    ->searchable(),
                TextColumn::make('account_number')
                    ->searchable(),
                TextColumn::make('status')
                    ->getStateUsing(fn($record) => ucfirst(strtolower($record->status)))
                    ->badge(fn($record) => match (strtolower($record->status)) {
                        'pending' => 'warning',
                        'processing' => 'primary',
                        'paid' => 'success',
                        'rejected' => 'danger',
                        default => 'secondary',
                    })
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
                Action::make('status')
                    ->label('Status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->form([
                        Select::make('status')
                            ->label('Status')
                            ->options(WithdrawalStatus::toKeyValue())
                            ->required()
                            ->preload(),

                        Textarea::make('reason_for_rejection')
                            ->label('Reason for Rejection')
                            ->required(fn($get) => strtolower($get('status')) === WithdrawalStatus::REJECTED->value),
                    ])
                    ->action(function ($record, $data) {
                        //if paid debit user wallet and send notification and mail
                        $notificationService = app(NotificationService::class);
                        if ($data['status'] === WithdrawalStatus::PAID->value) {
                            // Debit user wallet
                            $user = $record->user;
                            $walletService = new WalletService();
                            // if insufficient funds, throw error
                            if ($user->wallet->balance < $record->amount) {
                                throw new \Exception('Insufficient funds in user wallet');
                            }

                            $walletService->debitWallet($user, $record->amount);

                            self::sendMail(
                                user: $record->user,
                                subject: 'Your withdrawal request has been updated to ' . $data['status'],
                                view: 'email.withdrawal_successful',
                                extra: ['record' => $record]
                            );

                            $notificationService->createInAppNotification(
                                $record->user_id,
                                'Withdrawal update: ' . $data['status'],
                                "Your withdrawal request has been " . $data['status'] . ".",
                                'info',
                            );
                        }

                        if( $data['status'] === WithdrawalStatus::REJECTED->value) {
                            self::sendMail(
                                user: $record->user,
                                subject: 'Your withdrawal request has been updated to ' . $data['status'],
                                view: 'email.withdrawal_rejected',
                                extra: ['reason' => $data['reason_for_rejection'] ?? 'No reason provided', 'record' => $record]
                            );

                            $notificationService->createInAppNotification(
                                $record->user_id,
                                'Withdrawal update: ' . $data['status'],
                                "Your withdrawal request has been " . $data['status'] . ". Reason: " . ($data['reason_for_rejection'] ?? 'No reason provided'),
                                'danger',
                            );
                        }


                        $record->update(['status' => $data['status'], 'reason_for_rejection' => $data['reason_for_rejection'] ?? null]);
                    })
                    ->visible(fn($record) => strtolower($record->status) == strtolower(WithdrawalStatus::PENDING->value) || strtolower($record->status) == strtolower(WithdrawalStatus::PROCESSING->value)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
