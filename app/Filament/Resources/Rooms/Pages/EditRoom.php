<?php

namespace App\Filament\Resources\Rooms\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\Rooms\RoomResource;
use App\Models\ClassScheduleTemplate;
use App\Models\Room;
use App\Services\RoomService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditRoom extends EditRecord
{
    protected RoomService $roomService;

    protected static string $resource = RoomResource::class;

    public function boot(RoomService $service): void
    {
        $this->roomService = $service;
    }

    public function getTitle(): string
    {
        return 'Cập nhật phòng học';
    }
    protected function getHeaderActions(): array
    {
        return [

        ];
    }

    protected function getFormActions(): array
    {
        return [
            CommonAction::backAction(self::getResource()),
            $this->getSaveFormAction(),
        ];
    }

    protected function handleRecordUpdate(Model|Room $record, array $data): Model|Room
    {
        $result = $this->roomService->updateRoom($record, $data);
        if ($result->isError()) {
            Notification::make()
                ->danger()
                ->title('Lỗi cập nhật')
                ->body($result->getMessage())
                ->send();

            throw new Halt();
        }

        return $result->getData();
    }
}
