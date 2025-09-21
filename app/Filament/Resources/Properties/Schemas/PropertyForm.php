<?php

namespace App\Filament\Resources\Properties\Schemas;

use App\Enums\CurrencySign;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class PropertyForm
{
    public static function configure(Schema $schema): Schema
    {
        $record = $schema->getRecord();

        return $schema
            ->components([
                TextInput::make('Landlord')
                    ->formatStateUsing(function () use ($record) {
                        return $record->landlord->name ?? '';
                    })->disabled()->visible($record != null),

                Select::make('landlord_id')
                    ->options(User::where('role', User::ROLE_LANDLORD)->get()->pluck('email', 'id'))->required(),

                TextInput::make('title')
                    ->required(),
                Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                Select::make('property_type')
                    ->options([
                        '1_bedroom' => '1 bedroom',
                        '2_bedroom' => '2 bedroom',
                        '3_bedroom' => '3 bedroom',
                        '4_bedroom' => '4 bedroom',
                        'studio' => 'Studio',
                        'duplex' => 'Duplex',
                        'bungalow' => 'Bungalow',
                    ])
                    ->required(),
                TextInput::make('rent_amount')
                    ->required()
                    ->prefix(CurrencySign::NGN->value)
                    ->minValue(100)
                    ->numeric()
                    ->reactive()
                    ->afterStateUpdated(function ($set, $state) {
                        $commission = $state * 0.10;
                        $total = $state + $commission;

                        $set('shelterbaze_commission', number_format($commission, 2, '.', ''));
                        $set('total_amount', number_format($total, 2, '.', ''));
                    }),

                TextInput::make('shelterbaze_commission')
                    ->required()
                    ->numeric()
                    ->prefix(CurrencySign::NGN->value)
                    ->disabled(),

                TextInput::make('total_amount')
                    ->required()
                    ->numeric()
                    ->prefix(CurrencySign::NGN->value)
                    ->minValue(100)
                    ->default(0.0)
                    ->disabled(),

                Textarea::make('location_address')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('state')
                    ->required(),
                TextInput::make('lga')
                    ->required(),
                TextInput::make('longitude')
                    ->numeric(),
                TextInput::make('latitude')
                    ->numeric(),
                TextInput::make('facilities'),
                Select::make('status')
                    ->options(['open' => 'Open', 'closed' => 'Closed', 'rented' => 'Rented'])
                    ->default('open')
                    ->required(),
                Select::make('verification_status')
                    ->options(['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected'])
                    ->default('pending')
                    ->required(),

                // // Read-only text input to show the name
                TextInput::make('verified_by')
                    ->label('Verified By')
                    ->disabled()
                    ->visible($record != null),

                DateTimePicker::make('verified_at')
                    ->default(now())
                    ->dehydrated(true)
                    ->visible($record != null)->disabled(),

                Group::make([
                    // Property Images
                    FileUpload::make('property_images')
                        ->label('Property Images')
                        ->image()
                        ->multiple()
                        ->disk('local')
                        ->directory('temp/property-images')
                        ->visibility('private')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg', 'image/webp'])
                        ->maxSize(10240) // 10MB per image
                        ->maxFiles(20)
                        ->imagePreviewHeight('200')
                        ->panelLayout('grid')
                        ->panelAspectRatio('1:1')
                        ->uploadProgressIndicatorPosition('left')
                        ->helperText('Upload multiple property images (JPEG, PNG, JPG, WebP). Max 20 files, 10MB each.')
                        ->reorderable(),

                    // Property Videos
                    FileUpload::make('property_videos')
                        ->label('Property Videos')
                        ->multiple()
                        ->disk('local')
                        ->directory('temp/property-videos')
                        ->visibility('private')
                        ->acceptedFileTypes(['video/mp4', 'video/mov', 'video/avi', 'video/webm'])
                        ->maxSize(102400) // 100MB per video
                        ->maxFiles(5)
                        ->panelLayout('list')
                        ->uploadProgressIndicatorPosition('left')
                        ->helperText('Upload property videos (MP4, MOV, AVI, WebM). Max 5 files, 100MB each.')
                        ->reorderable(),
                ])->columnSpanFull(),

            ]);
    }
}
