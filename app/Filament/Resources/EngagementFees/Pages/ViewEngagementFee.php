<?php

namespace App\Filament\Resources\EngagementFees\Pages;

use App\Filament\Resources\EngagementFees\EngagementFeeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEngagementFee extends ViewRecord
{
    protected static string $resource = EngagementFeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
