<?php

namespace App\Filament\Resources\HelpCenterFaqs\Pages;

use App\Filament\Resources\HelpCenterFaqs\HelpCenterFaqResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHelpCenterFaqs extends ListRecords
{
    protected static string $resource = HelpCenterFaqResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
