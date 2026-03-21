<?php

namespace App\Filament\Resources\Rooms\Pages;

use App\Constants\RoomStatus;
use App\Filament\Components\CommonAction;
use App\Filament\Resources\Rooms\RoomResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateRoom extends CreateRecord
{
    protected static string $resource = RoomResource::class;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            CommonAction::backAction(self::getResource()),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Add additional validation or data manipulation here if needed
        return $data;
    }
}
