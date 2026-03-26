<?php

namespace App\Filament\Resources\Classes\Components;

use App\Constants\ScheduleStatus;
use App\Models\ScheduleInstance;
use App\Services\RoomService;
use App\Services\ScheduleService;
use App\Filament\Components\CustomSelect;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class ChangeRoomAction
{
    public static function make(): Action
    {
        return Action::make('change_room')
            ->label('Đổi phòng')
            ->icon(Heroicon::ArrowsRightLeft)
            ->color('warning')
            ->visible(fn (ScheduleInstance $record) =>
                $record->status === ScheduleStatus::Upcoming
            )
            ->form([
                CustomSelect::make('room_id')
                    ->label('Phòng học mới')
                    ->required()
                    ->getOptionSelectService(RoomService::class),
            ])
            ->action(function (ScheduleInstance $record, array $data) {

                $service = app(ScheduleService::class);

                $result = $service->changeRoom($record, $data['room_id']);

                if ($result->isError()) {
                    Notification::make()
                        ->danger()
                        ->title($result->getMessage())
                        ->send();
                    return;
                }

                Notification::make()
                    ->success()
                    ->title($result->getMessage() ?: 'Đổi phòng học thành công')
                    ->send();
            });
    }
}
