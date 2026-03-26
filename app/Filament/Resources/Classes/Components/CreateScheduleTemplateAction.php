<?php

namespace App\Filament\Resources\Classes\Components;

use App\Constants\DayOfWeek;
use App\Filament\Components\CustomSelect;
use App\Models\SchoolClass;
use App\Services\ClassScheduleService;
use App\Services\RoomService;
use App\Services\TeacherService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class CreateScheduleTemplateAction
{
    public static function make(): Action
    {
        return Action::make('create_schedule_template')
            ->label('Tạo lịch học cố định')
            ->icon(Heroicon::CalendarDays)
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Tạo mới lịch học cố định cho lớp')
            ->schema([
                Select::make('day_of_week')
                    ->label('Thứ trong tuần')
                    ->options(DayOfWeek::class)
                    ->required()
                    ->native(false),

                TimePicker::make('start_time')
                    ->label('Giờ bắt đầu')
                    ->native(false)
                    ->required(),

                TimePicker::make('end_time')
                    ->label('Giờ kết thúc')
                    ->native(false)
                    ->required(),

                CustomSelect::make('room_id')
                    ->label('Phòng học')
                    ->getOptionSelectService(RoomService::class)
                    ->required(),

                CustomSelect::make('teacher_id')
                    ->label('Giáo viên')
                    ->getOptionSelectService(TeacherService::class)
                    ->helperText("(Bỏ trống để dùng GV mặc định của lớp)"),

                DatePicker::make('start_date')
                    ->label('Ngày bắt đầu áp dụng')
                    ->default(now())
                    ->native(false)
                    ->required(),

                DatePicker::make('end_date')
                    ->label('Ngày kết thúc (Tùy chọn)')
                    ->native(false)
                    ->helperText('Để trống để vô thời hạn'),
            ])
            ->action(function (SchoolClass $record, array $data, ClassScheduleService $scheduleService) {
                // Determine teacher
                $data['teacher_id'] = $data['teacher_id'] ?? $record->teacher_id;

                $result = $scheduleService->createTemplate($record, $data);

                if ($result->isError()) {
                    Notification::make()
                        ->danger()
                        ->title('Lỗi hệ thống')
                        ->body($result->getMessage())
                        ->send();
                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Thành công')
                    ->body('Đã thêm lịch cố định và sinh các buổi học tương ứng.')
                    ->send();
            });
    }
}
