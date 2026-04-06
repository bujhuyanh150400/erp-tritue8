<?php

namespace App\Filament\Pages\ScheduleCalendar\Actions;

use App\Constants\DayOfWeek;
use App\Constants\ScheduleType;
use App\Filament\Components\CustomSelect;
use App\Helpers\FormatHelper;
use App\Models\ScheduleInstance;
use App\Models\SchoolClass;
use App\Repositories\ScheduleInstanceRepository;
use App\Services\ClassScheduleService;
use App\Services\RoomService;
use App\Services\TeacherService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ViewScheduleInstanceAction extends Action
{
    private bool $refreshCalendar = false;

    /**
     * Refresh calendar after action in view schedule detail
     * @return $this
     */
    public function refreshCalendar(): self
    {
        $this->refreshCalendar = true;
        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Chi tiết lịch học')
            ->modalHeading('Chi tiết lịch học')
            ->modalWidth('4xl')
            ->slideOver()
            ->record(fn(array $arguments, ScheduleInstanceRepository $instanceRepository) => $instanceRepository->query()->with([
                'class.activeEnrollments',
                'class.subject',
                'room',
                'teacher',
                'originalTeacher'
            ])->find($arguments['record_id']))
            ->schema([
                Section::make('Thông tin cơ bản')
                    ->icon('heroicon-m-information-circle')
                    ->compact()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('class_info')
                            ->label('Lớp & Mã lớp')
                            ->icon('heroicon-m-academic-cap')
                            ->state(fn($record) => "{$record->class?->name} ({$record->class?->code})")
                            ->copyable(), // Click vào để copy mã lớp

                        TextEntry::make('subject_grade')
                            ->label('Môn học & Khối')
                            ->icon('heroicon-m-book-open')
                            ->state(fn($record) => "{$record->class?->subject?->name} - Khối {$record->class?->grade_level->label()}"),

                        TextEntry::make('time_slot')
                            ->label('Thời gian')
                            ->icon('heroicon-m-clock')
                            ->state(fn($record) => \Carbon\Carbon::parse($record->date)->format('d/m/Y') . " | " . substr($record->start_time, 0, 5) . " - " . substr($record->end_time, 0, 5)),

                        TextEntry::make('teacher_name')
                            ->label('Giáo viên')
                            ->icon('heroicon-m-users')
                            ->state(function ($record) {
                                $teacherDisplay = $record->teacher?->full_name ?? 'Chưa phân công';
                                if ($record->original_teacher_id && $record->original_teacher_id !== $record->teacher_id) {
                                    $teacherDisplay .= " (Dạy thay cho: " . ($record->originalTeacher?->full_name ?? '...') . ")";
                                }
                                return $teacherDisplay;
                            }),

                        TextEntry::make('room.name')
                            ->label('Phòng học')
                            ->icon('heroicon-m-map-pin')
                            ->default('Chưa xếp phòng'),
                        TextEntry::make('total_students')
                            ->label('Sĩ số học sinh')
                            ->icon('heroicon-m-user-group')
                            ->state(fn($record) => $record->class?->activeEnrollments?->count() ?? 0),
                        TextEntry::make('schedule_type')
                            ->label('Thông tin lịch')
                            ->color(fn(ScheduleType $state) => $state->colorFilament())
                            ->badge(),
                        TextEntry::make('status')
                            ->label('Trạng thái')
                            ->badge(),
                        TextEntry::make('note')
                            ->label('Ghi chú')
                            ->columnSpanFull()
                            ->icon('heroicon-m-document-text')
                            ->default('Không có ghi chú'),

                    ]),

                Section::make('Thông tin tài chính')
                    ->icon('heroicon-m-banknotes')
                    ->columns(2)
                    ->compact()
                    ->visible(fn() => Auth::user()->isAdmin())
                    ->schema([
                        TextEntry::make('salary')
                            ->label('Lương GV ca này')
                            ->icon('heroicon-m-currency-dollar')
                            ->color('success') // Màu xanh lá cho lương
                            ->weight('bold') // Làm đậm
                            ->state(fn($record) => FormatHelper::formatPrice($record->custom_salary ?? $record->teacher_salary_snapshot) . ' VNĐ'),

                        TextEntry::make('fee')
                            ->label('Học phí ca này')
                            ->icon('heroicon-m-credit-card')
                            ->color('info') // Màu xanh dương cho học phí
                            ->weight('bold')
                            ->state(fn($record) => FormatHelper::formatPrice($record->custom_fee_per_session ?? $record->class?->base_fee_per_session ?? 0) . ' VNĐ'),
                    ]),
            ])
            ->extraModalFooterActions([
                // ACTION: CHỈNH SỬA LICH HỌC
                Action::make('edit')
                    ->label('Chỉnh sửa')
                    ->color('warning')
                    ->hidden(fn(ScheduleInstance $record) => $record->isDayOff())
                    ->overlayParentActions()
                    ->icon('heroicon-m-pencil-square')
                    ->fillForm(function (ScheduleInstance $record) {
                        return [
                            'date' => $record->date,
                            'start_time' => $record->start_time,
                            'end_time' => $record->end_time,
                            'room_id' => $record->room_id,
                            'teacher_id' => $record->teacher_id,
                        ];
                    })
                    ->schema([
                        Grid::make(2)
                            ->schema([
                            DatePicker::make('date')
                                ->label('Ngày học')
                                ->displayFormat('d/m/Y')
                                ->required(),
                            Grid::make(2)->schema([
                                TimePicker::make('start_time')
                                    ->label('Bắt đầu')
                                    ->seconds(false)
                                    ->required(),
                                TimePicker::make('end_time')
                                    ->label('Kết thúc')
                                    ->seconds(false)
                                    ->required(),
                            ])->columnSpan(1),

                            CustomSelect::make('room_id')
                                ->label('Phòng học')
                                ->getOptionSelectService(RoomService::class)
                                ->required(),

                            CustomSelect::make('teacher_id')
                                ->label('Giáo viên')
                                ->getOptionSelectService(TeacherService::class),
                        ]),
                    ])
                    ->action(function (array $data,  ScheduleInstance $record, ClassScheduleService $scheduleService, Component $livewire) {
                        $result = $scheduleService->updateInstance($record, $data);
                        if ($result->isError()){
                            Notification::make()
                                ->title('Cập nhật lịch học thất bại')
                                ->body($result->getMessage())
                                ->danger()
                                ->send();
                            throw new Halt();
                        }
                        Notification::make()->success()->title('Cập nhật lịch học thành công')->send();
                        if ($this->refreshCalendar){
                            $livewire->dispatch('filament-fullcalendar--refresh');
                        }
                    }),

                // ACTION: BÁO NGHỈ
                Action::make('report_absence')
                    ->label('Báo nghỉ / Nghỉ lễ')
                    ->hidden(fn(ScheduleInstance $record) => $record->isDayOff())
                    ->color('danger')
                    ->icon('heroicon-m-calendar-days')
                    ->overlayParentActions()
                    ->requiresConfirmation()
                    ->modalHeading('Xác nhận báo nghỉ')
                    ->modalDescription('Buổi học sẽ được chuyển thành lịch Nghỉ/Lễ. Vui lòng nhập lý do cụ thể.')
                    ->modalSubmitActionLabel('Xác nhận báo nghỉ')
                    // --- BƯỚC 1: CHẶN TRƯỚC KHI MỞ MODAL NẾU ĐÃ ĐIỂM DANH ---
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
                        if ($result->isError()){
                            Notification::make()
                                ->title('Báo nghỉ thất bại')
                                ->body($result->getMessage())
                                ->danger()
                                ->send();
                            throw new Halt();
                        }
                        Notification::make()->success()->title('Báo nghỉ thành công')->send();
                        if ($this->refreshCalendar){
                            $livewire->dispatch('filament-fullcalendar--refresh');
                        }
                    }),

                // ACTION: TÙY CHỈNH TÀI CHÍNH
                Action::make('custom_finance')
                    ->label('Tài chính')
                    ->hidden(fn(ScheduleInstance $record) => $record->isDayOff())
                    ->color('info')
                    ->icon('heroicon-m-banknotes')
                    ->visible(fn() => Auth::user()->isAdmin())
                    ->form([
                        \Filament\Forms\Components\TextInput::make('custom_salary')->numeric()->label('Lương thỏa thuận riêng ca này'),
                        \Filament\Forms\Components\TextInput::make('custom_fee_per_session')->numeric()->label('Học phí riêng ca này'),
                    ])
                    ->action(function (array $data, array $arguments) {
                        ScheduleInstance::find($arguments['record_id'])->update($data);
                        Notification::make()->success()->title('Đã cập nhật tài chính riêng')->send();
                    }),

                // ACTION: ĐIỀM DANH
                Action::make('go_to_attendance')
                    ->label('Vào điểm danh')
                    ->color('success')
                    ->hidden(fn(ScheduleInstance $record) => $record->isDayOff())
                    ->icon('heroicon-m-check-badge')
                    ->action(function (array $arguments) {
                        $si = ScheduleInstance::find($arguments['record_id']);
                        // Giả sử service của bạn xử lý logic session
                        // return redirect(AttendanceResource::getUrl('view', ['record' => ...]));
                    }),
            ])
            ->modalSubmitAction(false) // Ẩn nút submit mặc định của Modal lớn
            ->modalCancelActionLabel('Đóng');
    }
}
