<?php

namespace App\Filament\Resources\Rooms\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\Rooms\RoomResource;
use Filament\Resources\Pages\ListRecords;

class ListRooms extends ListRecords
{
    protected static string $resource = RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CommonAction::createAction("Tạo phòng học"),
        ];
    }
}
