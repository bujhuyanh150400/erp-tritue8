<?php

namespace App\Filament\Pages\ScheduleCalendar\Actions;

use App\Filament\Components\CommonNotification;
use App\Services\ClassScheduleService;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Livewire\Component;

class DragDropScheduleAction extends Action
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

        $this->modalHeading('Tùy chọn thay đổi lịch học')
            ->modalDescription(function (array $arguments) {
                // Format lại ngày tháng cho thân thiện với người Việt (d/m/Y)
                $date = Carbon::parse($arguments['new_date'])->format('d/m/Y');
                // Chỉ lấy Giờ:Phút để nhìn cho gọn
                $start = Carbon::parse($arguments['new_start'])->format('H:i');
                $end = Carbon::parse($arguments['new_end'])->format('H:i');

                return new HtmlString("
                    <div class='bg-gray-50 dark:bg-gray-800 p-3 rounded-lg border border-gray-200 dark:border-gray-700 mt-2'>
                        <p class='text-sm text-gray-600 dark:text-gray-400 mb-1'>Bạn đang thực hiện dời lịch sang:</p>
                        <div class='flex flex-wrap gap-2 items-center'>
                            <span class='px-2 py-1 bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300 rounded text-xs font-bold'>
                                📅 Ngày: {$date}
                            </span>
                            <span class='px-2 py-1 bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300 rounded text-xs font-bold'>
                                ⏰ Thời gian: {$start} - {$end}
                            </span>
                        </div>
                        <p class='text-sm text-gray-700 dark:text-gray-300 mt-3 font-medium'>
                            Vui lòng xác nhận tính chất của sự thay đổi này:
                        </p>
                    </div>
                ");
            })
            ->modalWidth(Width::Large)
            ->schema(fn (array $arguments) => [
                Radio::make('update_type')
                    ->hiddenLabel()
                    ->options(function () use ($arguments) {
                        $options = [
                            'one_time'  => '🕒 Thay đổi tạm thời (Chỉ đổi giờ/ngày của buổi học này)',
                            'permanent' => '🔄 Thay đổi lịch cố định (Đổi buổi này và ÁP DỤNG cho các tuần sau)',
                        ];
                        if (!isset($arguments['disable_makeup'])) {
                            $options['makeup'] = '➕ Tạo lịch bù (Báo nghỉ buổi hiện tại, tạo buổi học bù vào giờ mới)';
                        }
                        return $options;
                    })
                    ->descriptions(function () use ($arguments) {
                        $options = [
                            'one_time'  => 'Lịch các tuần sau vẫn giữ nguyên theo thời khóa biểu cũ.',
                            'permanent' => 'Sẽ cập nhật lại thời khóa biểu gốc của lớp học bắt đầu từ ' . now()->toDateString(),
                        ];
                        if (!isset($arguments['disable_makeup'])) {
                            $options['makeup'] = 'Buổi học cũ sẽ chuyển trạng thái "Nghỉ", và tạo buổi học bù vào giờ mới.';
                        }
                        return $options;
                    })
                    ->default('one_time')
                    ->live()
                    ->required(),

                Textarea::make('reason')
                    ->label('Lý do báo nghỉ')
                    ->placeholder('VD: Giáo viên ốm, Trung tâm mất điện...')
                    ->rows(2)
                    ->visible(fn (Get $get): bool => $get('update_type') === 'makeup')
                    ->required(fn (Get $get): bool => $get('update_type') === 'makeup'),
            ])
            ->action(function (array $data, array $arguments, ClassScheduleService $scheduleService, Component $livewire) {
                switch ($data['update_type']) {
                    case 'one_time':
                        $result = $scheduleService->moveInstance(
                            instanceId: $arguments['instance_id'],
                            newStart: $arguments['new_start'],
                            newEnd: $arguments['new_end'],
                        );
                        break;
                    case 'permanent':
                        $result = $scheduleService->moveInstanceAndChangeTemplate(
                            instanceId: $arguments['instance_id'],
                            newStart: $arguments['new_start'],
                            newEnd: $arguments['new_end'],
                        );
                        break;
                    case 'makeup':
                        if (isset($arguments['disable_makeup'])) {
                            CommonNotification::warning()
                                ->body('Bạn không thể tạo lịch bù".')
                                ->send();
                            throw new Halt();
                        }
                        if (empty(trim($data['reason']))) {
                            CommonNotification::warning()
                                ->body('Vui lòng cung cấp lý do báo nghỉ để tạo lịch bù.')
                                ->send();
                            throw new Halt();
                        }
                        $result = $scheduleService->cancelInstanceAndCreateMakeupInstance(
                            instanceId: $arguments['instance_id'],
                            newStart: $arguments['new_start'],
                            newEnd: $arguments['new_end'],
                            reason: $data['reason'],
                        );
                        break;
                    default:
                        CommonNotification::warning()
                            ->body('Vui lòng chọn một tùy chọn hợp lệ.')
                            ->send();
                        throw new Halt();
                }

                if ($result->isError()){
                    CommonNotification::error()
                        ->body($result->getMessage())
                        ->send();
                    throw new Halt();
                }
                CommonNotification::success()
                    ->body('Đã cập nhật lịch học.')
                    ->send();
                if ($this->refreshCalendar){
                    $livewire->dispatch('filament-fullcalendar--refresh');
                }
            });
    }
}
