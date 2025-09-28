<?php

namespace App\Filament\Resources\SupportTickets\Schemas;

use App\Models\User;
use App\Models\Property;
use App\Models\RentalAgreement;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Fieldset;
use Filament\Schemas\Schema;

class SupportTicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('ticket_number')
                    ->label('Ticket Number')
                    ->disabled()
                    ->placeholder('Auto-generated'),
                
                Select::make('user_id')
                    ->label('Customer')
                    ->options(function () {
                        return User::where('role', 'user')
                            ->get()
                            ->mapWithKeys(function ($user) {
                                return [$user->id => $user->first_name . ' ' . $user->last_name . ' (' . $user->email . ')'];
                            })
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('subject')
                    ->label('Subject')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Textarea::make('description')
                    ->label('Description')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),

                Select::make('ticket_type')
                    ->label('Ticket Type')
                    ->options([
                        'general' => 'General Inquiry',
                        'property_issue' => 'Property Issue',
                        'payment_issue' => 'Payment Issue',
                        'technical' => 'Technical Issue',
                        'account_issue' => 'Account Issue',
                    ])
                    ->required(),

                Select::make('priority')
                    ->label('Priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                    ])
                    ->default('medium')
                    ->required(),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'open' => 'Open',
                        'in_progress' => 'In Progress',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed',
                    ])
                    ->default('open')
                    ->required(),

                Select::make('assigned_to')
                    ->label('Assign To')
                    ->options(function () {
                        return User::where('role', 'admin')
                            ->get()
                            ->mapWithKeys(function ($user) {
                                return [$user->id => $user->first_name . ' ' . $user->last_name];
                            })
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->placeholder('Select an admin'),

                Select::make('property_id')
                    ->label('Related Property')
                    ->options(function () {
                        return Property::pluck('title', 'id')->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->placeholder('Select if property-related'),

                Select::make('rental_agreement_id')
                    ->label('Related Rental Agreement')
                    ->options(function () {
                        return RentalAgreement::with('property')
                            ->get()
                            ->mapWithKeys(function ($agreement) {
                                return [$agreement->id => ($agreement->property ? $agreement->property->title : 'Property N/A') . ' - Agreement #' . $agreement->id];
                            })
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->placeholder('Select if rental-related'),

                FileUpload::make('attachments')
                    ->label('Attachments')
                    ->multiple()
                    ->acceptedFileTypes(['image/*', 'application/pdf', '.doc', '.docx', '.txt'])
                    ->maxSize(5120) // 5MB
                    ->downloadable()
                    ->previewable()
                    ->reorderable()
                    ->columnSpanFull(),

                Textarea::make('resolution_notes')
                    ->label('Resolution Notes')
                    ->rows(3)
                    ->placeholder('Enter resolution details when ticket is resolved')
                    ->columnSpanFull(),

                DateTimePicker::make('resolved_at')
                    ->label('Resolution Date')
                    ->placeholder('Automatically set when resolved'),
            ]);
    }
}
