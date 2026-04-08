<?php

namespace App\Filament\Pages\ScheduleCalendar\Actions;

use App\Constants\FeeType;
use App\Constants\ScheduleType;
use App\Filament\Components\CustomSelect;
use App\Filament\Resources\AttendanceSessions\AttendanceSessionResource;
use App\Helpers\FormatHelper;
use App\Models\ScheduleInstance;
use App\Repositories\ScheduleInstanceRepository;
use App\Services\AttendanceService;
use App\Services\ClassScheduleService;
use App\Services\RoomService;
use App\Services\TeacherService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
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
                Action::make('edit_schedule_instance')
                    ->label('Chỉnh sửa')
                    ->color('warning')
                    ->visible(function ($record) {
                        return $record->canEditingInstance();
                    })
                    ->overlayParentActions()
                    ->icon(Heroicon::PencilSquare)
                    ->fillForm(function (ScheduleInstance $record) {
                        return [
                            'date' => $record->date,
                            'start_time' => $record->start_time,
                            'end_time' => $record->end_time,
                            'room_id' => $record->room_id,
                            'teacher_id' => $record->teacher_id,
                            'fee_type' => $record->fee_type,
                            'custom_fee_per_session' => $record->custom_fee_per_session,
                            'custom_salary' => $record->custom_salary,
                        ];
                    })
                    ->schema(fn(ScheduleInstance $record) => [
                        Section::make('Thời gian & Địa điểm')
                            ->compact()
                            ->schema([
                                DatePicker::make('date')
                                    ->label('Ngày học')
                                    ->displayFormat('d/m/Y')
                                    ->default(now())
                                    ->native(false)
                                    ->rules([
                                        Rule::date()->afterOrEqual($record->date),
                                    ])
                                    ->validationMessages([
                                        'after_or_equal' => 'Ngày học bù phải sau ngày báo nghỉ (' . Carbon::parse($record->date)->format('d/m/Y') . ').',
                                    ])
                                    ->required(),

                                Grid::make(2)->schema([
                                    TimePicker::make('start_time')
                                        ->label('Bắt đầu')
                                        ->seconds(false)
                                        ->native(false)
                                        ->required()
                                        // Ràng buộc để end_time có thể tham chiếu đến field này trong Closure
                                        ->live(),

                                    TimePicker::make('end_time')
                                        ->label('Kết thúc')
                                        ->seconds(false)
                                        ->native(false)
                                        ->required()
                                        // 2. Validate: end_time phải lớn hơn start_time
                                        ->rules([
                                            function (Get $get) {
                                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                    $startTime = $get('start_time');

                                                    // Chỉ validate nếu cả 2 trường đều đã có giá trị
                                                    if ($startTime && $value) {
                                                        $start = Carbon::parse($startTime);
                                                        $end = Carbon::parse($value);

                                                        if ($end->lessThanOrEqualTo($start)) {
                                                            $fail('Giờ kết thúc phải lớn hơn giờ bắt đầu.');
                                                        }
                                                    }
                                                };
                                            },
                                        ]),
                                ])->columnSpan(1),

                                CustomSelect::make('room_id')
                                    ->label('Phòng học')
                                    ->helperText("(Bỏ trống để dùng phòng mặc định của lớp)")
                                    ->getOptionSelectService(RoomService::class),

                                CustomSelect::make('teacher_id')
                                    ->label('Giáo viên')
                                    ->helperText("(Bỏ trống để dùng GV mặc định của lớp)")
                                    ->getOptionSelectService(TeacherService::class),
                            ]),
                        Section::make('Tài chính')
                            ->compact()
                            ->schema([
                                Radio::make('fee_type')
                                    ->label('Học phí học sinh')
                                    ->options(FeeType::class)
                                    ->default(FeeType::Normal)
                                    ->inline()
                                    ->live(),

                                TextInput::make('custom_fee_per_session')
                                    ->label('Học phí tùy chỉnh (VNĐ)')
                                    ->numeric()
                                    ->required()
                                    ->visible(fn(Get $get) => $get('fee_type') == FeeType::Custom),

                                TextInput::make('custom_salary')
                                    ->label('Lương GV ca này (VNĐ)')
                                    ->helperText('Để trống sẽ tính theo lương chuẩn của giáo viên.')
                                    ->numeric(),

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
                    ->visible(fn(ScheduleInstance $record) => $record->canEditingInstance())
                    ->color('danger')
                    ->icon(Heroicon::CalendarDays)
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


                // ACTION: ĐIỀM DANH
                Action::make('view_attendance')
                    ->label(fn(ScheduleInstance $record) => $record->hasAttendance() ? 'Xem điểm danh' : 'Bắt đầu điểm danh')
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
                            Notification::make()
                                ->title('Lỗi')
                                ->body($result->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->modalSubmitAction(false) // Ẩn nút submit mặc định của Modal lớn
            ->modalCancelActionLabel('Đóng');
    }
}
