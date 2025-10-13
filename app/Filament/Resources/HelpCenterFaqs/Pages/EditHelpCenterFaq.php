<?php

namespace App\Filament\Resources\HelpCenterFaqs\Pages;

use App\Filament\Resources\HelpCenterFaqs\HelpCenterFaqResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHelpCenterFaq extends EditRecord
{
    protected static string $resource = HelpCenterFaqResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
