<?php

namespace App\Filament\Resources\RentSavings\Pages;

use App\Filament\Resources\RentSavings\RentSavingResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRentSaving extends ViewRecord
{
    protected static string $resource = RentSavingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
