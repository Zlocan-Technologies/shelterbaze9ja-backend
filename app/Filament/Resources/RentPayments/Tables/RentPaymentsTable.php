<?php

namespace App\Filament\Resources\RentPayments\Tables;

use App\Models\AuditLog;
use App\Models\RentPayment;
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
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class RentPaymentsTable
{
    use SendMail;

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user')
                    ->label('User')
                    ->getStateUsing(fn($record) => $record->user ? $record->user->name : 'N/A')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')
                    ->numeric()
                    ->prefix('₦')
                    ->sortable(),
                TextColumn::make('payment_type'),
                TextColumn::make('bank_account_number')
                    ->searchable(),
                TextColumn::make('bank_name')
                    ->searchable(),
                TextColumn::make('account_name')
                    ->searchable(),
                TextColumn::make('payment_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('next_due_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status'),
                TextColumn::make('verified_by')
                    ->getStateUsing(fn($record) => $record->verifiedBy ? $record->verifiedBy->name : 'N/A')
                    ->sortable(),
                TextColumn::make('verified_at')
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

                Action::make('view_payment_proof')
                    ->label('View Payment Proof')
                    ->icon('heroicon-o-eye')
                    ->url(fn($record) => $record->payment_proof_url)
                    ->openUrlInNewTab()
                    ->visible(fn($record) => !empty($record->payment_proof_url)),

                //verify payment
                Action::make('verify_payment')
                    ->action(function ($record, $data) {
                        $notificationService = app(NotificationService::class);

                        $record->update([
                            'verified_by' => auth()->user()->id,
                            'verified_at' => now(),
                            'status' => $data['status']
                        ]);

                        //wallet will be funded when admin approves the payment
                        if ($data['status'] === RentPayment::STATUS_VERIFIED) {
                            $record->rentalAgreement->update([
                                'status' => 'active'
                            ]);

                            if ($data['payment_type'] === RentPayment::TYPE_ONLINE) {
                                $walletService = new WalletService();
                                $walletService->fundWallet($record->rentalAgreement->landlord, $record->amount);
                            }
                        }

                        Notification::make()
                            ->title('Payment Updated Successfully!')
                            ->info()
                            ->send();

                        AuditLog::log('rent_payment_updated to ' . $data['status'] . ' by ' . auth()->user()->name, $record);


                        //send mail to landlord and agent
                        // Send welcome email
                        self::sendMail(
                            user: $record->rentalAgreement->landlord,
                            subject: $record->user->name. ' has a payment ' . $data['status'],
                            view: 'email.rent_payment_status'
                        );

                        if($record->rentalAgreement->agent){
                            self::sendMail(
                                user: $record->rentalAgreement->agent,
                                subject: $record->user->name. ' has a payment ' . $data['status'],
                                view: 'email.rent_payment_status'
                            );
                        }

                        $notificationService->createInAppNotification(
                            $record->user_id,
                            'Your Payment Has Been ' . $data['status'],
                            "your payment for rent has been " . $data['status'] . ".",
                            'info',
                        );
                    })->visible(fn($record) => $record->status === 'pending')
                    ->form([
                        Section::make()->schema([
                            Select::make('status')
                                ->options([
                                    'pending' => 'Pending',
                                    'verified' => 'Verified',
                                    'rejected' => 'Rejected',
                                ])->required(),
                        ]),
                    ])
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
