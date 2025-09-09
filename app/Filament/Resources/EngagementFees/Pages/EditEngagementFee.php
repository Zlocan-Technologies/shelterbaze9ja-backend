<?php

namespace App\Filament\Resources\EngagementFees\Pages;

use App\Filament\Resources\EngagementFees\EngagementFeeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditEngagementFee extends EditRecord
{
    protected static string $resource = EngagementFeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
