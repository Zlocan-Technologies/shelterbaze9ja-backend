<?php

namespace App\Filament\Resources\RentPayments\Pages;

use App\Filament\Resources\RentPayments\RentPaymentResource;
use App\Filament\Widgets\RentPaymentsPageStatsWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRentPayments extends ListRecords
{
    protected static string $resource = RentPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            RentPaymentsPageStatsWidget::class,
        ];
    }
}
