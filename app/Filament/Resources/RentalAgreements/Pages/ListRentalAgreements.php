<?php

namespace App\Filament\Resources\RentalAgreements\Pages;

use App\Filament\Resources\RentalAgreements\RentalAgreementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRentalAgreements extends ListRecords
{
    protected static string $resource = RentalAgreementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
