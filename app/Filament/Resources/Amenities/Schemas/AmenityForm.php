<?php

namespace App\Filament\Resources\Amenities\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AmenityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('icon'),
                Select::make('type')
                    ->options(['general' => 'General', 'special' => 'Special'])
                    ->required(),
            ]);
    }
}
