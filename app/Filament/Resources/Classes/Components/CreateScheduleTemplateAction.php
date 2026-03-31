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
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Size;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;

class CreateScheduleTemplateAction
{
    public static function make(): Action
    {
        return Action::make('create_schedule_template')
            ->label('Tạo lịch học cố định')
            ->icon(Heroicon::CalendarDays)
            ->color('primary')
            ->size(Size::ExtraLarge)
            ->modalHeading('Tạo mới lịch học cố định cho lớp')
            ->modalDescription('Hệ thống sẽ tự động kiểm tra xung đột phòng học và giáo viên. Sau khi tạo, lịch học trong 4 tuần tới sẽ được sinh ra tự động. Các tuần tiếp theo sẽ được hệ thống tự động cập nhật vào mỗi Chủ Nhật hàng tuần')
            ->schema([
               Grid::make()->schema([
                   Select::make('days_of_week')
                       ->label('Thứ trong tuần')
                       ->multiple()
                       ->options(DayOfWeek::class)
                       ->required()
                       ->columnSpanFull()
                       ->native(false),

                   TimePicker::make('start_time')
                       ->label('Giờ bắt đầu')
                       ->required(),

                   TimePicker::make('end_time')
                       ->label('Giờ kết thúc')
                       ->required(),

                   CustomSelect::make('room_id')
                       ->label('Phòng học')
                       ->getOptionSelectService(RoomService::class)
                       ->required(),

                   CustomSelect::make('teacher_id')
                       ->label('Giáo viên')
                       ->serviceFilters(function (SchoolClass $record) {
                           return ['exclude_id' => $record->teacher_id];
                       })
                       ->getOptionSelectService(TeacherService::class)
                       ->helperText("(Bỏ trống để dùng GV mặc định của lớp)"),

                   DatePicker::make('start_date')
                       ->label('Ngày bắt đầu áp dụng')
                       ->helperText(function (SchoolClass $record) {
                           return "Ngày bắt đầu học không thể trước ngày khai giảng lớp học ({$record->start_at->format('d/m/Y')}).";
                       })
                       ->default(now())
                       ->native(false)
                       ->required(),

                   DatePicker::make('end_date')
                       ->label('Ngày kết thúc (Tùy chọn)')
                       ->native(false)
                       ->helperText(function (SchoolClass $record) {
                           if (!empty($record->end_at)) {
                               return "Ngày kết thúc học không thể sau ngày bế giảng lớp học ({$record->end_at->format('d/m/Y')}), Để trống sẽ tự tạo đến hết thời gian bế giảng.";
                           }
                           return "Để trống sẽ tự tạo vô thời hạn.";
                       }),
               ])
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
                    throw new Halt();
                }

                Notification::make()
                    ->success()
                    ->title('Thành công')
                    ->body('Đã thêm lịch cố định và sinh các buổi học tương ứng.')
                    ->send();
            });
    }
}
