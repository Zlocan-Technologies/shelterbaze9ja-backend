<?php

namespace App\Filament\Resources\RentalAgreements\Pages;

use App\Filament\Resources\RentalAgreements\RentalAgreementResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRentalAgreement extends ViewRecord
{
    protected static string $resource = RentalAgreementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
