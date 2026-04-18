<?php

namespace App\Filament\Pages\ScheduleCalendar\Actions;

use App\Filament\Components\CommonNotification;
use App\Repositories\ScheduleInstanceRepository;
use App\Services\ClassScheduleService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Livewire\Component;

class CreateMakeupScheduleAction extends Action
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

        $this->label('Tạo lịch học bù')
            ->icon(Heroicon::CalendarDays)
            ->color('warning')
            ->modalHeading('Tạo lịch học bù')
            ->modalDescription('Chọn buổi nghỉ cần bù, sau đó tạo lịch học bù theo ô lịch vừa chọn.')
            ->modalWidth(Width::Large)
            ->fillForm(function (array $arguments): array {
                $start = isset($arguments['selected_start'])
                    ? Carbon::parse($arguments['selected_start'])
                    : now();
                $end = isset($arguments['selected_end'])
                    ? Carbon::parse($arguments['selected_end'])
                    : $start->copy()->addHour();

                if ($end->lessThanOrEqualTo($start)) {
                    $end = $start->copy()->addHour();
                }

                return [
                    'date' => $start->toDateString(),
                    'start_time' => $start->format('H:i:s'),
                    'end_time' => $end->format('H:i:s'),
                ];
            })
            ->schema([
                Select::make('old_instance_id')
                    ->label('Buổi nghỉ cần bù')
                    ->helperText('Danh sách chỉ hiển thị các buổi nghỉ chưa có lịch học bù.')
                    ->options(function (): array {
                        /** @var ScheduleInstanceRepository $instanceRepository */
                        $instanceRepository = app(ScheduleInstanceRepository::class);

                        return $this->getCandidateOptions($instanceRepository);
                    })
                    ->searchable()
                    ->required()
                    ->validationMessages([
                        'required' => 'Vui lòng chọn buổi nghỉ cần bù.',
                    ]),

                DatePicker::make('date')
                    ->label('Ngày học bù')
                    ->native(false)
                    ->required(),

                TimePicker::make('start_time')
                    ->label('Giờ bắt đầu')
                    ->seconds(false)
                    ->native(false)
                    ->required(),

                TimePicker::make('end_time')
                    ->label('Giờ kết thúc')
                    ->seconds(false)
                    ->native(false)
                    ->rule('after:start_time')
                    ->validationMessages([
                        'after' => 'Giờ kết thúc phải lớn hơn giờ bắt đầu.',
                    ])
                    ->required(),
            ])
            ->action(function (
                array $data,
                ScheduleInstanceRepository $instanceRepository,
                ClassScheduleService $scheduleService,
                Component $livewire
            ): void {
                $oldInstanceId = (int) ($data['old_instance_id'] ?? 0);

                $oldInstance = $instanceRepository->getMakeupCandidateQuery()
                    ->where('id', $oldInstanceId)
                    ->first();

                if (!$oldInstance) {
                    CommonNotification::warning()
                        ->title('Không hợp lệ')
                        ->body('Buổi nghỉ đã chọn không còn hợp lệ để tạo lịch học bù.')
                        ->send();
                    throw new Halt();
                }

                $startTime = Carbon::parse((string) $data['start_time']);
                $endTime = Carbon::parse((string) $data['end_time']);
                if ($endTime->lessThanOrEqualTo($startTime)) {
                    CommonNotification::warning()
                        ->title('Không hợp lệ')
                        ->body('Giờ kết thúc phải lớn hơn giờ bắt đầu.')
                        ->send();
                    throw new Halt();
                }

                $result = $scheduleService->createMakeupInstance($oldInstance, [
                    'date' => Carbon::parse((string) $data['date'])->toDateString(),
                    'start_time' => $startTime->format('H:i:s'),
                    'end_time' => $endTime->format('H:i:s'),
                ]);

                if ($result->isError()) {
                    CommonNotification::error()
                        ->title('Tạo lịch bù thất bại')
                        ->body($result->getMessage())
                        ->send();
                    throw new Halt();
                }

                CommonNotification::success()
                    ->title('Tạo lịch bù thành công')
                    ->send();

                if ($this->refreshCalendar) {
                    $livewire->dispatch('filament-fullcalendar--refresh');
                }
            });
    }

    protected function getCandidateOptions(ScheduleInstanceRepository $instanceRepository): array
    {
        return $instanceRepository->getMakeupCandidateQuery()
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->mapWithKeys(function ($instance): array {
                return [(string) $instance->id => $this->formatCandidateLabel($instance)];
            })
            ->all();
    }

    protected function formatCandidateLabel($instance): string
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
