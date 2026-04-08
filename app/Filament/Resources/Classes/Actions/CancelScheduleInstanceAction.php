<?php

namespace App\Filament\Resources\Classes\Actions;

use App\Constants\ScheduleStatus;
use App\Models\ScheduleInstance;
use App\Services\ClassScheduleService;
use App\Services\ScheduleService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

class CancelScheduleInstanceAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Báo nghỉ')
            ->visible(function (ScheduleInstance $record) {
                // attendanceSession phải được khai báo with, tránh n+1 query
                return $record->canEditingInstance(); // Nếu là ngày nghỉ hoặc đã có dữ liệu điểm danh thì không thể báo nghỉ
            })
            ->color('danger')
            ->icon(Heroicon::CalendarDays)
            ->requiresConfirmation()
            ->modalHeading('Xác nhận báo nghỉ')
            ->modalDescription('Buổi học sẽ được chuyển thành lịch Nghỉ/Lễ. Vui lòng nhập lý do cụ thể.')
            ->modalSubmitActionLabel('Xác nhận báo nghỉ')
            ->mountUsing(function (ScheduleInstance $record, Action $action) {
                if ($record->attendanceSession()->exists()) {
                    Notification::make()
                        ->warning()
                        ->color('warning')
                        ->title('Thao tác bị chặn')
                        ->body('Buổi học đã có dữ liệu điểm danh.')
                        ->persistent()
                        ->send();
                    // Hủy mở Action Modal ngay lập tức
                    $action->cancel();
                }
            })
            ->schema([
                Textarea::make('reason')
                    ->label('Ghi chú / Lý do nghỉ')
                    ->helperText('Ví dụ: Giáo viên ốm, Nghỉ lễ Quốc Khánh...')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data, ScheduleInstance $record, ClassScheduleService $service, Component $livewire) {
                $result = $service->cancelInstance($record, $data['reason']);
                if ($result->isError()) {
                    Notification::make()
                        ->title('Báo nghỉ thất bại')
                        ->body($result->getMessage())
                        ->danger()
                        ->send();
                    throw new Halt();
                }
                Notification::make()->success()->title('Báo nghỉ thành công')->send();
            });
    }
}
