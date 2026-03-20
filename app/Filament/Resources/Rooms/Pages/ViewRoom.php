<?php

namespace App\Filament\Resources\Rooms\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\Rooms\RoomResource;
use Filament\Resources\Pages\ViewRecord;

class ViewRoom extends ViewRecord
{
    protected static string $resource = RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CommonAction::editAction(),
            CommonAction::backAction(self::getResource()),
        ];
    }
}
