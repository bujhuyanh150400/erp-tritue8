<?php

namespace App\Filament\Resources\Classes\Actions;

use App\Constants\DayOfWeek;
use App\Filament\Components\CommonNotification;
use App\Filament\Components\CustomSelect;
use App\Models\ClassScheduleTemplate;
use App\Services\ClassScheduleService;
use App\Services\RoomService;
use App\Services\TeacherService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Grid;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class EditScheduleTemplateAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Sửa')
            ->icon(Heroicon::CalendarDays)
            ->color('gray')
            ->button()
            ->modalHeading('Sửa lịch học cố định cho lớp')
            ->modalDescription('Hệ thống sẽ tự động kiểm tra xung đột phòng học và giáo viên. Sau khi sửa, sẽ thay đổi các lịch học sinh ra sớm dựa theo lịch cố định, còn các lịch học cũ sẽ được giữ nguyên')
            ->fillForm(function (ClassScheduleTemplate $record) {
                return [
                    'day_of_week' => $record->day_of_week,
                    'start_time' => Carbon::parse($record->start_time)->format('H:i'),
                    'end_time' => Carbon::parse($record->end_time)->format('H:i'),
                    'room_id' => $record->room_id,
                    'teacher_id' => $record->teacher_id,
                    'start_date' => Carbon::parse($record->start_date),
                    'end_date' => $record->end_date ? Carbon::parse($record->end_date) : null,
                ];
            })
            ->schema([
                Grid::make()->schema([
                    Select::make('day_of_week')
                        ->label('Ngày trong tuần')
                        ->native(false)
                        ->options(DayOfWeek::class)
                        ->required(),
                    TimePicker::make('start_time')
                        ->label('Giờ bắt đầu')
                        ->format("H:i")
                        ->displayFormat("H:i")
                        ->required(),

                    TimePicker::make('end_time')
                        ->label('Giờ kết thúc')
                        ->format("H:i")
                        ->displayFormat("H:i")
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
                        ->native(false),
                ])
            ])
            ->action(function (ClassScheduleTemplate $record, array $data, ClassScheduleService $scheduleService) {
                // Gọi hàm updateTemplate trong Service mà ta đã viết ở bước trước
                $result = $scheduleService->updateTemplate($record, $data);
                if ($result->isError()) {
                    CommonNotification::error()
                        ->title('Lỗi hệ thống')
                        ->body($result->getMessage())
                        ->send();

                    throw new Halt();
                }
                CommonNotification::success()
                    ->title('Thành công')
                    ->body('Sửa lịch học cố định cho lớp thành công')
                    ->send();
                return $record;
            });
    }


}
