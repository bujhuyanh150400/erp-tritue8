<?php

namespace App\Filament\Pages\ScheduleCalendar\Actions;

use App\Constants\ScheduleStatus;
use App\Constants\ScheduleType;
use App\Filament\Components\CommonNotification;
use App\Repositories\ScheduleInstanceRepository;
use App\Services\ClassScheduleService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

class CreateHolidayScheduleAction extends Action
{
    private bool $refreshCalendar = false;

    /**
     * Refresh calendar after action in admin calendar widget.
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

        $this->label('Nghỉ lễ nhiều lớp')
            ->icon(Heroicon::CalendarDays)
            ->color('danger')
            ->modalHeading('Thiết lập nghỉ lễ nhiều lớp')
            ->modalDescription('Chọn khoảng ngày, tick các lịch cần nghỉ (mặc định chọn tất cả), sau đó nhập lý do nghỉ.')
            ->modalWidth(Width::FiveExtraLarge)
            ->schema([
                Grid::make(2)->schema([
                    DatePicker::make('from_date')
                        ->label('Từ ngày')
                        ->default(now()->toDateString())
                        ->native(false)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set): void {
                            $set('schedule_instance_ids', $this->getDefaultSelectedScheduleIds(
                                $get('from_date'),
                                $get('to_date'),
                            ));
                        }),

                    DatePicker::make('to_date')
                        ->label('Đến ngày')
                        ->default(now()->toDateString())
                        ->native(false)
                        ->required()
                        ->live()
                        ->afterOrEqual('from_date')
                        ->afterStateUpdated(function (Get $get, Set $set): void {
                            $set('schedule_instance_ids', $this->getDefaultSelectedScheduleIds(
                                $get('from_date'),
                                $get('to_date'),
                            ));
                        }),
                ]),

                CheckboxList::make('schedule_instance_ids')
                    ->label('Danh sách lịch học trong khoảng thời gian')
                    ->helperText('Mặc định đã chọn tất cả lịch trong khoảng ngày. Bạn có thể bỏ chọn các lịch không muốn báo nghỉ.')
                    ->options(function (Get $get): array {
                        return $this->getScheduleOptions(
                            $get('from_date'),
                            $get('to_date'),
                        );
                    })
                    ->default(function (Get $get): array {
                        return $this->getDefaultSelectedScheduleIds(
                            $get('from_date'),
                            $get('to_date'),
                        );
                    })
                    ->bulkToggleable()
                    ->searchable()
                    ->columns(1)
                    ->required()
                    ->validationMessages([
                        'required' => 'Vui lòng chọn ít nhất 1 lịch cần nghỉ.',
                    ]),

                Textarea::make('reason')
                    ->label('Lý do nghỉ')
                    ->helperText('Ví dụ: Nghỉ lễ Quốc khánh, lịch trung tâm, hoạt động ngoại khóa...')
                    ->rows(3)
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data, ClassScheduleService $scheduleService, ScheduleInstanceRepository $instanceRepository, Component $livewire): void {
                $selectedIds = collect($data['schedule_instance_ids'] ?? [])
                    ->map(fn($id) => (int)$id)
                    ->filter()
                    ->values();

                if ($selectedIds->isEmpty()) {
                    CommonNotification::warning()
                        ->title('Thiếu dữ liệu')
                        ->body('Vui lòng chọn ít nhất 1 lịch cần nghỉ.')
                        ->send();
                    throw new Halt();
                }

                $instances = $instanceRepository->query()
                    ->whereIn('id', $selectedIds->all())
                    ->with(['class', 'teacher', 'room'])
                    ->orderBy('date')
                    ->orderBy('start_time')
                    ->get()
                    ->keyBy('id');

                $successCount = 0;
                $failedDetails = [];

                foreach ($selectedIds as $instanceId) {
                    $instance = $instances->get($instanceId);
                    if (!$instance) {
                        $failedDetails[] = "ID {$instanceId}: Không tìm thấy lịch học.";
                        continue;
                    }
                    if (!$instance->canEditingInstance()) {
                        $failedDetails[] = $this->formatInstanceLabel($instance) . ' - Lịch học này không thể báo nghỉ.';
                        continue;
                    }

                    $result = $scheduleService->cancelInstance($instance, trim($data['reason']));
                    if ($result->isError()) {
                        $failedDetails[] = $this->formatInstanceLabel($instance) . ' - ' . $result->getMessage();
                        continue;
                    }

                    $successCount++;
                }

                if ($successCount === 0) {
                    CommonNotification::error()
                        ->title('Báo nghỉ thất bại')
                        ->body($failedDetails[0] ?? 'Không có lịch nào được cập nhật.')
                        ->send();
                    throw new Halt();
                }

                CommonNotification::success()
                    ->title('Đã cập nhật lịch nghỉ')
                    ->body("Đã báo nghỉ {$successCount} lịch học.")
                    ->send();

                if (!empty($failedDetails)) {
                    $failedPreview = collect($failedDetails)->take(3)->implode("\n");
                    $remaining = count($failedDetails) - 3;

                    CommonNotification::warning()
                        ->title('Một số lịch chưa xử lý')
                        ->body($remaining > 0 ? "{$failedPreview}\n... và {$remaining} lịch khác." : $failedPreview)
                        ->send();
                }

                if ($this->refreshCalendar) {
                    $livewire->dispatch('filament-fullcalendar--refresh');
                }
            });
    }

    protected function getScheduleOptions(?string $fromDate, ?string $toDate): array
    {
        return $this->getSchedules($fromDate, $toDate)
            ->mapWithKeys(function ($instance): array {
                return [(string)$instance->id => $this->formatInstanceLabel($instance)];
            })
            ->all();
    }

    protected function getDefaultSelectedScheduleIds(?string $fromDate, ?string $toDate): array
    {
        return $this->getSchedules($fromDate, $toDate)
            ->pluck('id')
            ->map(fn($id) => (string)$id)
            ->all();
    }

    protected function getSchedules(?string $fromDate, ?string $toDate): Collection
    {
        if (empty($fromDate) || empty($toDate)) {
            return collect();
        }

        $start = Carbon::parse($fromDate)->toDateString();
        $end = Carbon::parse($toDate)->toDateString();

        if ($start > $end) {
            return collect();
        }

        /** @var ScheduleInstanceRepository $instanceRepository */
        $instanceRepository = app(ScheduleInstanceRepository::class);

        return $instanceRepository->query()
            ->with(['class', 'teacher', 'room'])
            ->whereNotNull('class_id')
            ->whereBetween('date', [$start, $end])
            ->where('status', ScheduleStatus::Upcoming->value)
            ->where('schedule_type', '!=', ScheduleType::Holiday->value)
            ->whereDoesntHave('attendanceSession')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
    }

    protected function formatInstanceLabel($instance): string
    {
        $date = Carbon::parse($instance->date)->format('d/m/Y');
        $start = Carbon::parse($instance->start_time)->format('H:i');
        $end = Carbon::parse($instance->end_time)->format('H:i');
        $className = $instance->class?->name ?? 'Không có lớp';
        $teacherName = $instance->teacher?->full_name ?? 'Chưa phân công GV';
        $roomName = $instance->room?->name ?? 'Chưa xếp phòng';

        return "{$date} | {$start}-{$end} | {$className} | GV: {$teacherName} | Phòng: {$roomName}";
    }
}
