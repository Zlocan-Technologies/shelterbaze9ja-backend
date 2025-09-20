<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Basic Information
                TextInput::make('first_name')
                    ->required(),
                TextInput::make('last_name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('phone_number')
                    ->tel()
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->required()
                    ->hiddenOn('edit'),
                Select::make('role')
                    ->options(['user' => 'User', 'landlord' => 'Landlord', 'agent' => 'Agent', 'admin' => 'Admin'])
                    ->default('user')
                    ->required(),

                // Account Status
                DateTimePicker::make('email_verified_at')
                    ->formatStateUsing(fn($record) => $record->email_verified_at ?? ''),
                DateTimePicker::make('phone_verified_at')
                    ->formatStateUsing(fn($record) => $record->phone_verified_at ?? ''),
                Toggle::make('profile_completed'),
                Select::make('account_status')
                    ->options([
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'declined' => 'Declined',
                        'suspended' => 'Suspended',
                    ])
                    ->default('pending')
                    ->required(),

                // Profile Information
                TextInput::make('profile.nin_number')
                    ->formatStateUsing(fn($record) => $record->profile->nin_number ?? '')
                    ->label('NIN Number')
                    ->maxLength(11)
                    ->placeholder('Enter 11-digit NIN'),

                Placeholder::make('profile.nin_selfie_preview')
                    ->content(function ($record) {
                        $existingUrl = $record->profile->nin_selfie_url ?? null;
                        return new \Illuminate\Support\HtmlString('<div style="margin-bottom: 16px;"><strong>Current NIN Selfie:</strong><br><img src="' . $existingUrl . '" style="max-width: 200px; max-height: 150px; border-radius: 8px; margin-top: 8px;" alt="Current NIN Selfie"></div>');
                    })->visible(function ($record) {
                        return $record != null && $record?->profile?->nin_selfie_url != null;
                    }),

                FileUpload::make('profile.nin_selfie_url')
                    ->label('NIN Selfie (Upload New)')
                    ->image()
                    ->disk('local')
                    ->directory('temp')
                    ->visibility('private')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg'])
                    ->maxSize(5120) // 5MB
                    ->imagePreviewHeight('250')
                    ->loadingIndicatorPosition('left')
                    ->panelAspectRatio('2:1')
                    ->panelLayout('integrated')
                    ->removeUploadedFileButtonPosition('right')
                    ->uploadButtonPosition('left')
                    ->uploadProgressIndicatorPosition('left')
                    ->helperText('Upload a clear selfie with your NIN document'),

                Textarea::make('profile.address')
                    ->formatStateUsing(fn($record) => $record->profile->address ?? '')
                    ->label('Address')
                    ->rows(3)
                    ->placeholder('Enter full address'),

                TextInput::make('profile.state')
                    ->formatStateUsing(fn($record) => $record->profile->state ?? '')
                    ->label('State')
                    ->placeholder('Enter state'),

                TextInput::make('profile.lga')
                    ->label('Local Government Area (LGA)')
                    ->formatStateUsing(fn($record) => $record->profile->lga ?? '')
                    ->placeholder('Enter LGA'),

                TextInput::make('profile.agent_id')
                    ->label('Agent ID')
                    ->placeholder('Auto-generated for agents')
                    ->formatStateUsing(fn($record) => $record->profile->agent_id ?? '')
                    ->disabled()
                    ->visible(fn($record) => ($record != null && $record?->role === User::ROLE_AGENT))
                    ->dehydrated(false),

                Placeholder::make('profile.id_card_preview')
                    ->content(function ($record) {
                        $existingUrl = $record->profile->id_card_url ?? null;
                        return new \Illuminate\Support\HtmlString('<div style="margin-bottom: 16px;"><strong>Current ID Card/Document:</strong><br><img src="' . $existingUrl . '" style="max-width: 200px; max-height: 150px; border-radius: 8px; margin-top: 8px;" alt="Current ID Card"></div>');
                    })->visible(function ($record) {
                        return $record != null && $record?->profile?->id_card_url != null;
                    }),

                FileUpload::make('profile.id_card_url')
                    ->label('ID Card/Document (Upload New)')
                    ->image()
                    ->disk('local')
                    ->directory('temp')
                    ->visibility('private')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'])
                    ->maxSize(5120) // 5MB
                    ->imagePreviewHeight('250')
                    ->loadingIndicatorPosition('left')
                    ->panelAspectRatio('2:1')
                    ->panelLayout('integrated')
                    ->removeUploadedFileButtonPosition('right')
                    ->uploadButtonPosition('left')
                    ->uploadProgressIndicatorPosition('left')
                    ->helperText('Upload a clear photo of your ID card or other government-issued document'),

                KeyValue::make('profile.verification_documents')
                    ->label('Additional Verification Documents')
                    ->formatStateUsing(fn($record) => $record->profile->verification_documents ?? [])
                    ->keyLabel('Document Type')
                    ->valueLabel('Document URL/Details')
                    ->addActionLabel('Add Document'),
            ]);
    }
}
