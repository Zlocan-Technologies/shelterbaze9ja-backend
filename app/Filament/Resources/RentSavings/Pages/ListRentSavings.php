<?php

namespace App\Filament\Resources\RentSavings\Pages;

use App\Filament\Resources\RentSavings\RentSavingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRentSavings extends ListRecords
{
    protected static string $resource = RentSavingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
