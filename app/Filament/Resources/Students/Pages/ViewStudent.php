<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\Students\StudentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewStudent extends ViewRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CommonAction::editAction(),
            CommonAction::backAction(self::getResource()),
        ];
    }
}
