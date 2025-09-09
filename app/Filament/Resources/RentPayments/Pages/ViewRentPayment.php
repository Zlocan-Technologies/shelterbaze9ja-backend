<?php

namespace App\Filament\Resources\RentPayments\Pages;

use App\Filament\Resources\RentPayments\RentPaymentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRentPayment extends ViewRecord
{
    protected static string $resource = RentPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
