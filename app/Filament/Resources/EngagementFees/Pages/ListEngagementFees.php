<?php

namespace App\Filament\Resources\EngagementFees\Pages;

use App\Filament\Resources\EngagementFees\EngagementFeeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEngagementFees extends ListRecords
{
    protected static string $resource = EngagementFeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
