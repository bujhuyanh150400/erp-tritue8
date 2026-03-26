<?php

namespace App\Filament\Resources\Classes\Components;

use App\Constants\ScheduleStatus;
use App\Models\ScheduleInstance;
use App\Services\ScheduleService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class CancelScheduleInstanceAction
{
    public static function make(): Action
    {
        return Action::make('cancel_session')
            ->label('Hủy buổi')
            ->icon(Heroicon::XCircle)
            ->color('danger')
            ->visible(fn (ScheduleInstance $record) => $record->status === ScheduleStatus::Upcoming)
            ->modalHeading('Xác nhận hủy buổi học')
            ->form(function (ScheduleInstance $record) {

                $isUrgent = now()->diffInHours(
                        $record->date->copy()->setTimeFromTimeString($record->start_time),
                        false
                    ) < 6;

                return [

                    Placeholder::make('cancel_info')
                        ->content(
                            "Bạn sắp hủy buổi học ngày {$record->date->format('d/m/Y')} " .
                            "({$record->start_time} - {$record->end_time}) tại phòng {$record->room->name}, " .
                            "GV {$record->teacher->full_name}."
                        ),

                    Placeholder::make('warning')
                        ->content('Buổi học sắp diễn ra (< 6 giờ). Bạn có muốn gửi thông báo khẩn không?')
                        ->visible($isUrgent),

                    Checkbox::make('urgent_notify')
                        ->label('Gửi thông báo khẩn cho học sinh')
                        ->visible($isUrgent),

                    Textarea::make('reason')
                        ->label('Lý do hủy'),

                    Checkbox::make('is_fee_counted')
                        ->label('Vẫn tính tiền buổi học này cho học sinh')
                        ->default(false),
                ];
            })
            ->action(function (ScheduleInstance $record, array $data) {

                $service = app(ScheduleService::class);

                $result = $service->cancelSession($record, $data);

                if ($result->isError()) {
                    Notification::make()
                        ->danger()
                        ->title($result->getMessage())
                        ->send();
                    return;
                }

                Notification::make()
                    ->success()
                    ->title($result->getMessage() ?: 'Đã hủy buổi học')
                    ->send();
            });
    }
}
