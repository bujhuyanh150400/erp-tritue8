<?php

namespace App\Filament\Resources\ScheduleInstance\Actions;

use App\Filament\Components\CommonNotification;
use App\Filament\Resources\AttendanceSessions\AttendanceSessionResource;
use App\Models\ScheduleInstance;
use App\Services\AttendanceService;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class ViewOrCreateAttendanceAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn(ScheduleInstance $record) => $record->hasAttendance() ? 'Xem điểm danh' : 'Bắt đầu điểm danh')
            ->icon(Heroicon::ClipboardDocumentCheck)
            ->hidden(fn(ScheduleInstance $record) => $record->isDayOff())
            ->color(fn(ScheduleInstance $record) => $record->attendanceSession ? 'info' : 'success')
            ->action(function (ScheduleInstance $record, AttendanceService $attendanceService) {
                $result = $attendanceService->startOrGetSession($record);
                if ($result->isSuccess()) {
                    $data = $result->getData();
                    $this->redirect(AttendanceSessionResource::getUrl('view', ['record' => $data]));
                } else {
                    // Bắt lỗi Validation và hiện Notification góc phải
                    CommonNotification::error()
                        ->body($result->getMessage())
                        ->send();
                }
            });
    }
}
