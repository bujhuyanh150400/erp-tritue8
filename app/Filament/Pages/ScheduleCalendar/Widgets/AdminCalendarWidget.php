<?php

namespace App\Filament\Pages\ScheduleCalendar\Widgets;

use App\Filament\Pages\ScheduleCalendar\Actions\DragDropScheduleAction;
use App\Filament\Pages\ScheduleCalendar\Actions\ViewScheduleInstanceAction;
use App\Models\ScheduleInstance;
use App\Services\AttendanceService;
use App\Services\ClassScheduleService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Width;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class AdminCalendarWidget extends FullCalendarWidget
{
    protected ClassScheduleService $scheduleService;
    protected AttendanceService $attendanceService;

    protected string $view = 'filament.pages.admin-calendar-widget';

    // Mặc định bật filter chỉ hiển thị lớp Active
    public array $filters = ['active_classes_only' => true];

    // Danh sách giáo viên đang có lịch
    public array $activeTeachers = [];

    public function boot(ClassScheduleService $scheduleService, AttendanceService $attendanceService): void
    {
        $this->scheduleService = $scheduleService;
        $this->attendanceService = $attendanceService;
    }

    /**
     * CẤU HÌNH GIAO DIỆN LỊCH (Spec 6h-22h)
     */
    public function config(): array
    {
        $user = Auth::user();
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
            'editable' => $user->isAdmin(),
            'selectable' => $user->isAdmin(),
            'eventResizableFromStart' => $user->isAdmin(),
            'handleWindowResize' => true,
            'views' => [
                'listWeek' => [
                    'buttonText' => 'Danh sách tuần',
                ],
            ],
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
        if ($result->isSuccess()) {
            $events = $result->getData();
            $teachersMap = [];
            foreach ($events as $event) {
                // Kiểm tra xem buổi học có giáo viên không
                $extendProps = $event['extendedProps'];
                if ($extendProps['teacher_id']) {
                    $teachersMap[$extendProps['teacher_id']] = [
                        'name' => $extendProps['teacher'] ?? 'N/A',
                        'color' => $extendProps['teacher_color'],
                    ];
                }
            }
            $this->dispatch('update-teachers', data: array_values($teachersMap));
            return $events;
        } else {
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
            function ({ event, el }) {
                const props = event.extendedProps;
                // Hàm format nhanh H:i:s -> H:i
                 let startTime = props.start_time ? props.start_time.substring(0, 5) : '';
                let endTime = props.end_time ? props.end_time.substring(0, 5) : '';
                const content = `
                    📅 Lớp: \${props.class}<br/>
                    📚 Môn: \${props.subject_name}<br/>
                    🕐 Thời gian: \${startTime} - \${endTime}<br/>
                    👨‍🏫 GV: \${props.teacher}<br/>
                    🚪 Phòng: \${props.room_name}<br/>
                    👥 Sĩ số: \${props.active_students_count}<br/>
                    📌 Trạng thái điểm danh: \${props.status_attendance_label}<br/>
                    📌 Kiểu lịch: \${props.schedule_type_label}
                `.replace(/\\n/g, ' ').trim();
                el.setAttribute("x-data", "{ tooltip: '" + content.replace(/'/g, "\\'") + "' }");
                el.setAttribute("x-tooltip.html", "tooltip");
            }
        JS;
    }

    /**
     * Định nghĩa nội dung event
     * @return string
     * @return string
     */
    public function eventContent(): string
    {
        return <<<'JS'
           function (arg) {
                let props = arg.event.extendedProps;
                // Format lại thời gian
                let startTime = props.start_time ? props.start_time.substring(0, 5) : '';
                let endTime = props.end_time ? props.end_time.substring(0, 5) : '';

                // Sử dụng props.teacher_color để đổ màu nền động
                let html = `
                    <div class="flex flex-col h-full w-full p-1 text-white rounded text-xs leading-tight overflow-hidden transition"
                         style="background-color: ${props.teacher_color}; filter: brightness(95%);">

                         <div class="font-bold border-b border-white/30 pb-0.5 mb-0.5 whitespace-nowrap">
                            🕒 ${startTime} - ${endTime}
                        </div>

                        <div class="font-bold truncate uppercase text-[14px]">
                            ${props.subject_name}
                        </div>

                        <div class="truncate opacity-90 text-[14px]">
                            🚪 ${props.room_name}
                        </div>
                        <div class="truncate opacity-90 text-[14px]">
                            👨‍🎓 ${props.class}
                        </div>
                        <div class="truncate opacity-90 text-[14px]">
                            📌 ${props.schedule_type_label}
                        </div>
                    </div>
                `;
                return { html: html };
        }
        JS;
    }

    protected function headerActions(): array
    {
        return [
            Action::make('create_schedule_quick')
                ->modalHeading('Tạo lịch học mới')
                ->modalWidth('md')

                // TỰ ĐỘNG ĐIỀN DATA VÀO FORM DỰA TRÊN KHOẢNG GIỜ VỪA KÉO
                ->mountUsing(function ($form, array $arguments) {
                    if (isset($arguments['start']) && isset($arguments['end'])) {
                        $start = Carbon::parse($arguments['start']);
                        $end = Carbon::parse($arguments['end']);
                        $form->fill([
                            'date' => $start->format('Y-m-d'),
                            'start_time' => $start->format('H:i'),
                            'end_time' => $end->format('H:i'),
                        ]);
                    }
                })

                // ĐỊNH NGHĨA CÁC TRƯỜNG NHẬP LIỆU
                ->form([
                    DatePicker::make('date')
                        ->label('Ngày học')
                        ->required(),

                    Grid::make(2)->schema([
                        TimePicker::make('start_time')
                            ->label('Giờ bắt đầu')
                            ->required(),
                        TimePicker::make('end_time')
                            ->label('Giờ kết thúc')
                            ->required(),
                    ]),
                    // Bác tự thêm các trường khác (teacher_id, room_id,...) vào đây nhé
                ])

                // XỬ LÝ LƯU DATABASE
                ->action(function (array $data) {
                    // Lưu vào database
                    // \App\Models\ScheduleInstance::create($data);

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Thành công')
                        ->body('Đã tạo lịch học thành công!')
                        ->send();

                    // Load lại lịch ngay lập tức
                    $this->refreshRecords();
                })
        ];
    }

    protected function modalActions(): array
    {
        return [
            // Xem chi tiết lịch học
            ViewScheduleInstanceAction::make('view_schedule_detail')
                ->refreshCalendar(),

            DragDropScheduleAction::make('change_schedule_action')
                ->refreshCalendar(),

        ];
    }

    /**
     * XỬ LÝ CHỌN NGÀY HỌC
     * @param string $start
     * @param string|null $end
     * @param bool $allDay
     * @param array|null $view
     * @param array|null $resource
     * @return void
     */
    public function onDateSelect(string $start, ?string $end, bool $allDay, ?array $view, ?array $resource): void
    {
        // Gọi Action 'create_schedule_quick' và truyền thời gian qua
        $this->mountAction('create_schedule_quick', [
            'start' => $start,
            'end' => $end,
        ]);
    }

    /**
     * XỬ LÝ CLICK LỊCH HỌC
     * @param array $event
     * @return void
     */
    public function onEventClick(array $event): void
    {
        $this->mountAction('view_schedule_detail', [
            'record_id' => $event['id']
        ]);
    }

    /**
     * XỬ LÝ Resize GIỜ HỌC
     */
    public function onEventResize(array $event, array $oldEvent, array $relatedEvents, array $startDelta, array $endDelta): bool
    {
        $newStart = Carbon::parse($event['start']);
        $newEnd = Carbon::parse($event['end']);
        // Xử lý góc khuất FullCalendar: Nếu sự kiện kết thúc đúng 00:00:00 hôm sau
        // (tức là kéo hết ngày hiện tại), ta trừ đi 1 giây để so sánh cho chuẩn.
        $checkEnd = $newEnd->copy();
        if ($checkEnd->format('H:i:s') === '00:00:00') {
            $checkEnd->subSecond();
        }
        // Kiểm tra xem có bị lấn sang ngày khác không
        if (!$newStart->isSameDay($checkEnd)) {
            Notification::make()
                ->warning()
                ->title('Thao tác bị chặn')
                ->body('Bạn không thể kéo dài buổi học lấn sang ngày khác.')
                ->send();
            return true;
        }

        $result = $this->scheduleService->editTimeInstance($event['id'], $newStart, $newEnd);

        if ($result->isError()) {
            Notification::make()
                ->danger()
                ->title('Lỗi')
                ->body($result->getMessage())
                ->send();
            return true;
        }
        Notification::make()
            ->success()
            ->title('Đã đổi giờ học thành công!')
            ->send();

        $this->refreshRecords();
        return false;
    }

    /**
     * XỬ LÝ KÉO THẢ ĐỔI GIỜ
     */
    public function onEventDrop(array $event, array $oldEvent, array $relatedEvents, array $delta, ?array $oldResource, ?array $newResource): bool
    {
        $newStart = Carbon::parse($event['start']);
        $newEnd = Carbon::parse($event['end']);

        $this->mountAction('change_schedule_action',[
            'instance_id' => $event['id'],
            'new_date' => $newStart->copy()->format('d-m-Y'),
            'new_start'   => $newStart,
            'new_end'     => $newEnd,
        ]);
        // Trả về true để ngăn chặn FullCalendar from updating the event in the DOM
        return true;
    }
}
