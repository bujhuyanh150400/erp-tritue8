<?php

namespace App\Filament\Resources\Rooms\Pages;

use App\Constants\RoomStatus;
use App\Filament\Components\CommonAction;
use App\Filament\Resources\Rooms\RoomResource;
use App\Services\RoomService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateRoom extends CreateRecord
{
    protected RoomService $roomService;

    protected static string $resource = RoomResource::class;

    public function boot(RoomService $service): void
    {
        $this->roomService = $service;
    }

    public function getTitle(): string
    {
        return 'Tạo phòng học';
    }
    protected function getFormActions(): array
    {
        return [
            CommonAction::backAction(self::getResource()),
            $this->getCreateFormAction(),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        $result = $this->roomService->createRoom($data);

        if ($result->isError() || !$result->getData()) {
            Notification::make()
                ->danger()
                ->title('Không thể tạo phòng học')
                ->body($result->getMessage() ?: 'Lỗi không xác định')
                ->send();
            throw new Halt();
        }

        // chắc chắn return 1 Eloquent Model
        return $result->getData();
    }

}
