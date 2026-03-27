<?php

namespace App\Filament\Widgets;

use App\Models\ScheduleInstance;
use App\Services\ScheduleService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class AdminCalendarWidget extends FullCalendarWidget
{
    // Mặc định bật filter chỉ hiển thị lớp Active
    public array $filters = ['active_classes_only' => true];

    protected ScheduleService $scheduleService;

    public function boot(ScheduleService $scheduleService): void
    {
        $this->scheduleService = $scheduleService;
    }

    /**
     * CẤU HÌNH GIAO DIỆN LỊCH (Spec 6h-22h)
     */
    public function config(): array
    {
        return [
            'headerToolbar' => [
                'left' => 'title',
                'center' => 'dayGridMonth,timeGridWeek,timeGridDay listYear',
                'right' => 'today prev,next',
            ],
            'initialView' => 'timeGridWeek',
            'slotMinTime' => '06:00:00',
            'slotMaxTime' => '22:00:00',
            'allDaySlot' => false,
            'locale' => 'vi',
            'editable' => false,
            'selectable' => false,
        ];
    }

    /**
     * HỨNG SỰ KIỆN TỪ PAGE:
     * Mỗi khi bạn chọn bộ lọc trên form, nó sẽ nhảy vào hàm này và tải lại lịch
     */
    #[On('updateCalendarFilters')]
    public function updateFilters(array $filters): void
    {
        $this->filters = $filters;
        $this->refreshRecords();
    }

    protected function headerActions(): array
    {
        return [];
    }

    protected function modalActions(): array
    {
        return [];
    }

    /**
     * TRUY VẤN DỮ LIỆU & ÁP DỤNG BỘ LỌC
     */
    public function fetchEvents(array $info): array
    {
        $start = Carbon::parse($info['start']);
        $end = Carbon::parse($info['end']);
        $filters = $this->filters;
        $result = $this->scheduleService->getScheduleInstancesCalendar(
            start: $start,
            end: $end,
            filters: $filters,
        );
        if ($result->isSuccess()){
            return $result->getData();
        }else{
            Notification::make()
                ->title('Lỗi')
                ->body($result->getMessage())
                ->send();
            return [];
        }
    }

    /**
     * Khi event được mount lên calendar, thêm tooltip cho mỗi event
     * @return string
     */
    public function eventDidMount(): string
    {
        return <<<JS
            function({ event, el }) {
                const p = event.extendedProps;
                const content = `
                    📅 Lớp: \${p.class}<br/>
                    📚 Môn: \${p.subject}<br/>
                    🕐 Thời gian: \${p.start_time} - \${p.end_time}<br/>
                    👨‍🏫 GV: \${p.teacher}<br/>
                    🚪 Phòng: \${p.room}<br/>
                    👥 Sĩ số: \${p.si_so}
                `.replace(/\\n/g, ' ').trim();
                el.setAttribute("x-data", "{ tooltip: '" + content.replace(/'/g, "\\'") + "' }");
                el.setAttribute("x-tooltip.html", "tooltip");
            }
        JS;
    }

    /**
     * DỮ LIỆU CHO POPUP: Khai báo Model khi click vào một Block Lịch
     */
    public function resolveEventRecord(array $data): Model
    {
        return ScheduleInstance::query()
            ->with(['class.subject', 'teacher', 'originalTeacher', 'room', 'linkedMakeupFor'])
            ->withCount('classEnrollments as si_so')
            ->findOrFail($data['id']);
    }

    /**
     * GIAO DIỆN POPUP CHI TIẾT
     */
    protected function viewAction(): Action
    {
//        return Action::make('view')
//            ->modalHeading('Chi tiết buổi học')
//            ->modalSubmitAction(false) // Ẩn nút Submit vì chỉ để xem
//            ->modalCancelActionLabel('Đóng')
//
//            // 1. Nạp dữ liệu vào các ô Input
//            ->fillForm(function (array $arguments) {
//                // Query y hệt SQL Spec
//                $si = ScheduleInstance::query()
//                    ->with(['class.subject', 'teacher', 'originalTeacher', 'room', 'makeupFor'])
//                    ->withCount('classEnrollments as si_so')
//                    ->find($arguments['event']['id']);
//                return [
//                    'class_info' => $si->class ? "{$si->class->name} ({$si->class->code})" : '',
//                    'subject_name' => $si->class->subject->name ?? '',
//                    'teacher_name' => $si->teacher->full_name ?? '',
//                    'original_teacher_name' => $si->originalTeacher->full_name ?? 'Không đổi',
//                    'room_name' => $si->room->name ?? '',
//                    'si_so' => $si->si_so ?? 0,
//                    'schedule_type' => $si->schedule_type,
//                    'status' => $si->status,
//                    'fee_type' => $si->fee_type,
//                    'custom_fee_per_session' => number_format($si->custom_fee_per_session ?? 0),
//                    'teacher_salary_snapshot' => number_format($si->teacher_salary_snapshot ?? 0),
//                    'custom_salary' => number_format($si->custom_salary ?? 0),
//                    'makeup_date' => $si->makeupFor->date ?? 'Không có',
//                    'note' => $si->note ?? 'Không có ghi chú',
//                ];
//            })
//
//            // 2. Dựng giao diện bằng Schema Components
//            ->schema([
//                Grid::make(2)->schema([
//                    TextInput::make('class_info')->label('Lớp (Mã)')->readOnly(),
//                    TextInput::make('subject_name')->label('Môn học')->readOnly(),
//                    TextInput::make('teacher_name')->label('GV Hiện tại')->readOnly(),
//                    TextInput::make('original_teacher_name')->label('GV Gốc')->readOnly(),
//                    TextInput::make('room_name')->label('Phòng học')->readOnly(),
//                    TextInput::make('si_so')->label('Sĩ số hiện tại')->readOnly(),
//                    TextInput::make('schedule_type')->label('Loại lịch')->readOnly(),
//                    TextInput::make('status')->label('Trạng thái')->readOnly(),
//                ]),
//                Section::make('Tài chính & Ghi chú')->schema([
//                    Grid::make(2)->schema([
//                        TextInput::make('fee_type')->label('Kiểu học phí')->readOnly(),
//                        TextInput::make('custom_fee_per_session')->label('Học phí Custom (VND)')->readOnly(),
//                        TextInput::make('teacher_salary_snapshot')->label('Lương GV Snapshot (VND)')->readOnly(),
//                        TextInput::make('custom_salary')->label('Lương GV Custom (VND)')->readOnly(),
//                    ]),
//                    TextInput::make('makeup_date')->label('Dạy bù cho ngày')->readOnly(),
//                    TextInput::make('note')->label('Ghi chú')->readOnly(),
//                ])
//            ]);

        return Action::make('view')
            ->modalHeading('Chi tiết buổi học')
            ->modalWidth('sm')

            // 1. Nút "Chi tiết" - Mở ra một Modal khác hoặc Redirect
            ->extraModalFooterActions([
                Action::make('view_detail')
                    ->label('Xem chi tiết')
                    ->color('gray')
                    ->icon('heroicon-m-eye')
                    // Nếu bạn muốn mở trang chi tiết của Lớp/Buổi học:
                    ->close(),
                // 2. Nút "Chỉnh sửa" - Chuyển sang trang Edit
                Action::make('edit_schedule')
                    ->label('Chỉnh sửa lịch')
                    ->color('primary')
                    ->icon('heroicon-m-pencil-square')
                    ->close(),
            ])

            // Ẩn các nút mặc định của Modal gốc để trông giống một Menu hơn
            ->modalSubmitAction(false)
            ->modalCancelAction(false);
    }

    /**
     * XỬ LÝ KÉO THẢ ĐỔI GIỜ
     */
    public function onEventDrop(array $event, array $oldEvent, array $relatedEvents, array $delta, ?array $oldResource, ?array $newResource): bool
    {
        $instance = ScheduleInstance::find($event['id']);

        if ($instance) {
            $newStart = Carbon::parse($event['start']);
            $newEnd = Carbon::parse($event['end']);

            $instance->update([
                'date' => $newStart->toDateString(),
                'start_time' => $newStart->toTimeString(),
                'end_time' => $newEnd->toTimeString(),
            ]);

            \Filament\Notifications\Notification::make()
                ->success()
                ->title('Đã đổi giờ học thành công!')
                ->send();
        }

        // BẮT BUỘC: Trả về false để FullCalendar giữ nguyên vị trí mới của Block lịch.
        // Nếu trả về true, Block lịch sẽ tự động giật ngược lại vị trí cũ (rất tiện để check trùng lịch sau này).
        return false;
    }
}
