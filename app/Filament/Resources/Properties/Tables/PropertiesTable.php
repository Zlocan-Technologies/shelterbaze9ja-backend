<?php

namespace App\Filament\Resources\Properties\Tables;

use App\Models\AgentAssignment;
use App\Models\User;
use App\Repositories\AgentRepository;
use App\Services\NotificationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PropertiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('first_image')->label('Image')
                    ->getStateUsing(function ($record) {
                        return  $record->images()->first()->media_url ?? null;
                    })
                    ->extraAttributes([
                        'class' => 'w-24 h-24 object-cover',
                    ]),

                TextColumn::make('landlord_id')
                    ->label("Landlord")
                    ->getStateUsing(function ($record) {
                        return  $record->landlord->name;
                    })
                    ->sortable(),

                TextColumn::make('agent_verifier')
                    ->getStateUsing(function ($record) {
                        return $record->agentAssignments()
                            ->where('assignment_type', AgentAssignment::TYPE_PROPERTY_VERIFICATION)
                            ->latest()
                            ->first()?->agent?->name ?? 'N/A';
                    }),
                TextColumn::make('agent_support')
                    ->getStateUsing(function ($record) {
                        return $record->agentAssignments()
                            ->where('assignment_type', AgentAssignment::TYPE_LANDLORD_SUPPORT)
                            ->latest()
                            ->first()?->agent?->name ?? 'N/A';
                    }),

                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('property_type'),
                TextColumn::make('rent_amount')
                    ->numeric()
                    ->sortable(),
                // TextColumn::make('shelterbaze_commission')
                //     ->numeric()
                //     ->sortable(),
                // TextColumn::make('total_amount')
                //     ->numeric()
                //     ->sortable(),
                TextColumn::make('state')
                    ->searchable(),
                TextColumn::make('lga')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'info' => 'open',
                        'success' => 'rented',
                        'danger' => 'closed',
                    ]),

                TextColumn::make('verification_status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'verified',
                        'danger' => 'rejected',
                    ]),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),

                Action::make('verify')
                    ->action(function ($record, array $data) {
                        $record->update([
                            'verified_by' => auth()->user()->id,
                            'verified_at' => now(),
                            'verification_status' => $data['verification_status']
                        ]);
                    })
                    ->form([
                        Section::make()
                            ->columns([
                                'sm' => 2,
                                'xl' => 2,
                                '2xl' => 2,
                            ])->schema([
                                Select::make('verification_status')
                                    ->options(['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected'])
                                    ->default('pending')
                                    ->required(),
                            ]),
                    ])
                    ->color('info')
                    ->icon('heroicon-o-check'),

                Action::make('assign_agent')
                    ->action(function ($record, array $data) {
                        $agentRepository = app(AgentRepository::class);

                        $agentAssignment = $agentRepository->assignAgent(
                            $record->id,
                            $data['agent_id'],
                            $data['assign_type'],
                            $record->landlord_id
                        );

                        if (!$agentAssignment) {
                            Notification::make()
                                ->title('This agent is already assigned to this property for the selected role.')
                                ->info()
                                ->send();
                            return;
                        }

                        $notificationService = new NotificationService();

                        if ($data['assign_type'] === AgentAssignment::TYPE_LANDLORD_SUPPORT) {
                            // Notify landlord support team
                            $record->update([
                                'agent_id' => $data['agent_id'],
                            ]);

                            $notificationService->createInAppNotification(
                                $data['agent_id'],
                                'New Property Assigned',
                                "A new property '{$record->title}' has been assigned to you for landlord support.",
                                'info',
                            );

                            $notificationService->createInAppNotification(
                                $record->landlord_id,
                                'Agent Assigned to Your Property',
                                "An agent has been assigned to your property '{$record->title}'. They will assist you with any inquiries.",
                                'info',
                            );
                        } elseif ($data['assign_type'] === AgentAssignment::TYPE_PROPERTY_VERIFICATION) {
                            // Notify property verification team
                            $notificationService->createInAppNotification(
                                $data['agent_id'],
                                'New Property Assigned for Verification',
                                "A new property '{$record->title}' has been assigned to you for verification.",
                                'info',
                            );

                            $notificationService->createInAppNotification(
                                $record->landlord_id,
                                'Agent Assigned to Verify Your Property',
                                "An agent has been assigned to verify your property '{$record->title}'. They will contact you shortly.",
                                'info',
                            );
                        }

                        Notification::make()
                            ->title('Agent assigned successfully')
                            ->success()
                            ->send();
                    })
                    ->form([
                        Section::make()
                            ->columns([
                                'sm' => 2,
                                'xl' => 2,
                                '2xl' => 2,
                            ])->schema([
                                Select::make('agent_id')
                                    ->options(User::where('role', User::ROLE_AGENT)->get()->pluck('email', 'id'))
                                    ->required(),

                                Select::make('assign_type')
                                    ->options([
                                        AgentAssignment::TYPE_LANDLORD_SUPPORT => 'Landlord Support',
                                        AgentAssignment::TYPE_PROPERTY_VERIFICATION => 'Property Verification',
                                    ])->required(),
                            ]),
                    ])
                    ->color('secondary')
                    ->icon('heroicon-o-user'),

                Action::make('remove_agent')
                    ->action(function ($record, $data) {
                        $notificationService = app(NotificationService::class);

                        $agentRepository = app(AgentRepository::class);
                        $agentAssignment = $agentRepository->unAssignAgent(
                            $record->id,
                            $data['assign_type']
                        );

                        if (!$agentAssignment) {
                            Notification::make()
                                ->title('No active assignment found for this agent and role.')
                                ->info()
                                ->send();
                            return;
                        }

                        if ($data['assign_type'] === AgentAssignment::TYPE_LANDLORD_SUPPORT) {
                            $record->update([
                                'agent_id' => null,
                            ]);

                            if ($record->agent_id != null) {
                                $notificationService->createInAppNotification(
                                    $record->agent_id,
                                    'Property Unassigned',
                                    "The property '{$record->title}' has been unassigned from you.",
                                    'info',
                                );
                            }
                        } elseif ($data['assign_type'] === AgentAssignment::TYPE_PROPERTY_VERIFICATION) {
                            if ($record->agent_id != null) {
                                $notificationService->createInAppNotification(
                                    $record->agent_id,
                                    'Property Unassigned from Verification',
                                    "The property '{$record->title}' has been unassigned from you.",
                                    'info',
                                );
                            }

                            $notificationService->createInAppNotification(
                                $record->landlord_id,
                                'Agent Unassigned from Your Property',
                                "The agent assigned to verify your property '{$record->title}' has been unassigned.",
                                'info',
                            );
                        }


                        Notification::make()
                            ->title('Agent unassigned successfully')
                            ->success()
                            ->send();
                    })
                    ->form([
                        Section::make()->schema([
                            Select::make('assign_type')
                                ->options([
                                    'landlord_support' => 'Landlord Support',
                                    'property_verification' => 'Property Verification',
                                ])->required(),
                        ]),
                    ])
                    ->requiresConfirmation()
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                // ->visible(function ($record) {
                //     return $record->agent_id !== null;
                // }),


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
