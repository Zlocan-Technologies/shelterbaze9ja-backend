<?php

namespace App\Filament\Resources\Withdrawals\Pages;

use App\Filament\Resources\Withdrawals\WithdrawalResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWithdrawal extends ViewRecord
{
    protected static string $resource = WithdrawalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
