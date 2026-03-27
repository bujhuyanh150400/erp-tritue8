<?php

namespace App\Filament\Resources\AttendanceSessions\Pages;

use App\Filament\Resources\AttendanceSessions\AttendanceSessionResource;
use App\Models\ScheduleInstance;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendanceSession extends CreateRecord
{
    protected static string $resource = AttendanceSessionResource::class;

    /**
     * Public title required by Filament
     */
    public function getTitle(): string
    {
        return 'Bắt đầu điểm danh';
    }

    /**
     * Giữ schedule_instance_id để sử dụng trước khi insert
     */
    public ?string $scheduleInstanceId = null;

    /**
     * Mount: điền sẵn các trường form từ ScheduleInstance nếu có query param
     */
    public function mount(): void
    {
        parent::mount();

        $this->scheduleInstanceId = request()->query('schedule_instance_id');

        if ($this->scheduleInstanceId) {
            $scheduleInstance = ScheduleInstance::find($this->scheduleInstanceId);

            if ($scheduleInstance) {
                $this->form->fill([
                    'class_id' => $scheduleInstance->class_id,
                    'teacher_id' => $scheduleInstance->teacher_id,
                    'room_id' => $scheduleInstance->room_id,
                    'date' => $scheduleInstance->date,
                    'start_time' => $scheduleInstance->start_time,
                    'end_time' => $scheduleInstance->end_time,
                ]);
            }
        }
    }

    /**
     * Trước khi tạo record, đảm bảo schedule_instance_id được điền
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($this->scheduleInstanceId) {
            $data['schedule_instance_id'] = $this->scheduleInstanceId;
        }

        return $data;
    }
}
