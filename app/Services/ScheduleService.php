<?php

namespace App\Services;

use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Core\Logs\Logging;
use App\Models\ScheduleInstance;
use App\Repositories\ScheduleInstanceRepository;
use App\Constants\ScheduleStatus;
use App\Constants\ScheduleType;
use App\Constants\FeeType;
use Illuminate\Support\Carbon;
use Saade\FilamentFullCalendar\Data\EventData;

class ScheduleService extends BaseService
{

    public function __construct(
        protected ScheduleInstanceRepository $scheduleInstanceRepository,
    ){}

    public function checkConflict(array $data): ServiceReturn
    {
        return $this->execute(function () use ($data) {

            $roomConflict = $this->scheduleInstanceRepository->findRoomConflicts(
                $data['room_id'],
                $data['date'],
                $data['start_time'],
                $data['end_time'],
                $data['ignore_id'] ?? null
            );

            if ($roomConflict) {
                throw new ServiceException(
                    "Phòng đã có lớp {$roomConflict->name} từ {$roomConflict->start_time} - {$roomConflict->end_time}"
                );
            }

            $teacherConflict = $this->repository->findTeacherConflict(
                $data['teacher_id'],
                $data['date'],
                $data['start_time'],
                $data['end_time'],
                $data['ignore_id'] ?? null
            );

            if ($teacherConflict) {
                throw new ServiceException(
                    "Giáo viên đang có lớp {$teacherConflict->name} từ {$teacherConflict->start_time} - {$teacherConflict->end_time}"
                );
            }

            return true;
        });
    }

    public function changeRoom(ScheduleInstance $record, int $roomId): ServiceReturn
    {
        return $this->execute(function () use ($record, $roomId) {

            $conflictCheck = $this->checkConflict([
                'room_id' => $roomId,
                'teacher_id' => $record->teacher_id,
                'date' => $record->date,
                'start_time' => $record->start_time,
                'end_time' => $record->end_time,
                'ignore_id' => $record->id,
            ]);

            if ($conflictCheck->isError()) {
                throw new ServiceException($conflictCheck->getMessage());
            }

            $oldRoom = $record->room->name ?? '';

            $this->repository->updateRoom($record->id, $roomId);

            Logging::userActivity(
                action: 'Đổi phòng học',
                description: "Đổi phòng từ {$oldRoom} sang phòng ID {$roomId}, buổi ngày {$record->date}"
            );

            return 'Đổi phòng học thành công';
        });
    }

    public function cancelSession(ScheduleInstance $record, array $data): ServiceReturn
    {
        return $this->execute(function () use ($record, $data) {

            $this->repository->cancelSchedule($record->id, $data);

            if (!($data['is_fee_counted'] ?? false)) {
                $this->repository->updateAttendanceFee($record->id);
            }

            if (!empty($data['urgent_notify'])) {
                // TODO: dispatch job
            }

            Logging::userActivity(
                action: 'Hủy buổi học',
                description: "Hủy buổi ngày {$record->date} lớp {$record->class_id}"
                . (!empty($data['reason']) ? " | Lý do: {$data['reason']}" : '')
            );

            return 'Đã hủy buổi học';

        }, useTransaction: true);
    }

    public function createMakeupSession(ScheduleInstance $record, array $data): ServiceReturn
    {
        return $this->execute(function () use ($record, $data) {

            if (strtotime($data['start_time']) >= strtotime($data['end_time'])) {
                throw new ServiceException('Giờ bắt đầu phải nhỏ hơn giờ kết thúc');
            }

            if ($data['fee_type'] == FeeType::Custom->value
                && empty($data['custom_fee_per_session'])) {
                throw new ServiceException('Vui lòng nhập học phí tùy chỉnh');
            }

            $conflictCheck = $this->checkConflict([
                'room_id' => $data['room_id'],
                'teacher_id' => $data['teacher_id'],
                'date' => $data['date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
            ]);

            if ($conflictCheck->isError()) {
                throw new ServiceException($conflictCheck->getMessage());
            }

            $salarySnapshot = $this->repository->getSalarySnapshot(
                $data['teacher_id'],
                $record->class_id,
                $data['date']
            ) ?? $record->class->teacher_salary_per_session;

            $this->repository->createScheduleInstance([
                'class_id' => $record->class_id,
                'date' => $data['date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'room_id' => $data['room_id'],
                'teacher_id' => $data['teacher_id'],
                'original_teacher_id' => $data['teacher_id'],
                'teacher_salary_snapshot' => $salarySnapshot,
                'custom_salary' => $data['custom_salary'] ?? null,
                'schedule_type' => ScheduleType::Makeup->value,
                'status' => ScheduleStatus::Upcoming->value,
                'linked_makeup_for' => $record->id,
                'fee_type' => $data['fee_type'],
                'custom_fee_per_session' => $data['custom_fee_per_session'] ?? null,
                'created_by' => auth()->id(),
            ]);

            Logging::userActivity(
                action: 'Tạo buổi bù',
                description: "Tạo buổi bù lớp {$record->class_id} ngày {$data['date']}"
            );

            return 'Tạo buổi bù thành công';

        }, useTransaction: true);
    }
}
