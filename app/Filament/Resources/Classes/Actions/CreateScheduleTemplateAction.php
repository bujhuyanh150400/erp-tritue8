<?php

namespace App\Filament\Resources\Classes\Actions;

use App\Constants\DayOfWeek;
use App\Filament\Components\CommonNotification;
use App\Filament\Components\CustomSelect;
use App\Models\SchoolClass;
use App\Services\ClassScheduleService;
use App\Services\RoomService;
use App\Services\TeacherService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Size;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;

class CreateScheduleTemplateAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Tạo lịch học cố định')
            ->icon(Heroicon::CalendarDays)
            ->color('primary')
            ->modalHeading('Tạo mới lịch học cố định cho lớp')
            ->modalDescription('Hệ thống sẽ tự động kiểm tra xung đột phòng học và giáo viên. Sau khi tạo, lịch học trong 4 tuần tới sẽ được sinh ra tự động. Các tuần tiếp theo sẽ được hệ thống tự động cập nhật vào mỗi Chủ Nhật hàng tuần')
            ->schema([
               Grid::make()->schema([
                   CheckboxList::make('days_of_week')
                       ->label('Thứ trong tuần')
                       ->options(DayOfWeek::class)
                       ->required()
                       ->columns(3)
                       ->columnSpanFull(),

                   TimePicker::make('start_time')
                       ->format("H:i")
                       ->displayFormat("H:i")
                       ->label('Giờ bắt đầu')
                       ->required(),

                   TimePicker::make('end_time')
                       ->format("H:i")
                       ->displayFormat("H:i")
                       ->label('Giờ kết thúc')
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
                $result = $scheduleService->createTemplate($record, $data);

                if ($result->isError()) {
                    CommonNotification::error()
                        ->title('Lỗi hệ thống')
                        ->body($result->getMessage())
                        ->send();
                    throw new Halt();
                }

                CommonNotification::success()
                    ->title('Thành công')
                    ->body('Đã thêm lịch cố định và sinh các buổi học tương ứng.')
                    ->send();
            });
    }
}
