<?php

namespace App\Filament\Resources\AgentAssignments\Pages;

use App\Filament\Resources\AgentAssignments\AgentAssignmentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAgentAssignment extends ViewRecord
{
    protected static string $resource = AgentAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
