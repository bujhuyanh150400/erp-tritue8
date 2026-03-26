<?php

namespace App\Filament\Resources\AttendanceSessions\Pages;

use App\Filament\Resources\AttendanceSessions\AttendanceSessionResource;
use App\Models\ScheduleInstance;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendanceSession extends CreateRecord
{
    protected static string $resource = AttendanceSessionResource::class;

    // MUST be public
    public function getTitle(): string
    {
        return 'Bắt đầu điểm danh';
    }

    public function mount(): void
    {
        parent::mount();

        $scheduleInstanceId = request()->query('schedule_instance_id');
        if ($scheduleInstanceId) {
            $scheduleInstance = ScheduleInstance::find($scheduleInstanceId);

            if ($scheduleInstance) {
                // Điền sẵn các trường cần thiết vào form
                $this->form->fill([
                    'schedule_instance_id' => $scheduleInstance->id,
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
}
