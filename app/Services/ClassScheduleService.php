<?php

namespace App\Services;

use App\Constants\DayOfWeek;
use App\Constants\FeeType;
use App\Constants\ScheduleStatus;
use App\Constants\ScheduleType;
use App\Models\ScheduleInstance;
use Filament\Support\Colors\Color;
use App\Core\Logs\Logging;
use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Jobs\GenerateScheduleInstancesJob;
use App\Models\ClassScheduleTemplate;
use App\Models\SchoolClass;
use App\Repositories\ClassScheduleTemplateRepository;
use App\Repositories\TeacherSalaryConfigRepository;
use App\Repositories\ScheduleInstanceRepository;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Saade\FilamentFullCalendar\Data\EventData;

class ClassScheduleService extends BaseService
{
    public function __construct(
        protected ClassScheduleTemplateRepository $templateRepository,
        protected ScheduleInstanceRepository      $instanceRepository,
        protected TeacherSalaryConfigRepository   $teacherSalaryConfigRepository
    )
    {
    }

    /**
     * ---- Protected Methods ----
     */

    /**
     * Kiểm tra xung đột thời gian với PHÒNG và GIÁO VIÊN trong khoảng thời gian
     * @param int $roomId ID Phòng
     * @param int $teacherId ID Giáo viên
     * @param array $dayValues Mảng ngày trong tuần, thuộc DayOfWeek::class
     * @param string $startTime Thời gian bắt đầu
     * @param string $endTime Thời gian kết thúc
     * @param string $startDate Ngày bắt đầu kiểm tra
     * @param string $endDate Ngày kết thúc kiểm tra
     * @param int|null $excludeInstanceId ID buổi học thực tế không kiểm tra
     * @return void
     * @throws ServiceException -- Nếu xung đột xảy ra
     */
    protected function checkConflictInstances(
        int $roomId,
        int $teacherId,
        array $dayValues,
        string $startTime,
        string $endTime,
        string $startDate,
        string $endDate,
        int|null $excludeTemplateId = null,
        int|null $excludeInstanceId = null,

    ): void
    {
        // Kiểm tra trùng lịch cố định (Templates)
        $templateConflict = $this->templateRepository->findConflicts(
            roomId: $roomId,
            teacherId: $teacherId,
            daysOfWeek: $dayValues,
            startTime: $startTime,
            endTime: $endTime,
            startDate: $startDate,
            endDate: $endDate,
            excludeTemplateId: $excludeTemplateId,
        );
        if ($templateConflict) {
            throw new ServiceException("Thứ {$templateConflict->day_of_week->label()}: Lịch bị vướng bởi lớp [{$templateConflict->class->name}].");
        }

        // Kiểm tra trùng lịch thực tế (Instances)
        $instanceConflict = $this->instanceRepository->findConflicts(
            roomId: $roomId,
            teacherId: $teacherId,
            daysOfWeek: $dayValues,
            startTime: $startTime,
            endTime: $endTime,
            startDate: $startDate,
            endDate: $endDate,
            excludeInstanceId: $excludeInstanceId,
        );
        if ($instanceConflict) {
            $dayName = Carbon::parse($instanceConflict->date)->dayOfWeekIso;
            throw new ServiceException("Thứ {$dayName}: Vướng lịch thực tế của lớp [{$instanceConflict->class->name}] ngày " . Carbon::parse($instanceConflict->date)->format('d/m/Y'));
        }
    }


    /**
     * ---- Public Methods ----
     */

    /**
     * Tạo lịch cố định (Template)
     * @param SchoolClass $class
     * @param array $data
     * @return ServiceReturn
     */
    public function createTemplate(SchoolClass $class, array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($class, $data) {
                $roomId = $data['room_id'];
                $teacherId = $data['teacher_id'] ?? $class->teacher_id;
                $startTime = $data['start_time'];
                $endTime = $data['end_time'];
                $startDate = Carbon::parse($data['start_date'])->format('Y-m-d');
                // Nếu template không có ngày kết thúc, coi như vô hạn
                $endDate = $data['end_date'] ? Carbon::parse($data['end_date'])->format('Y-m-d') : '2099-12-31';

                // Lấy ngày tuần từ dữ liệu
                $daysOfWeek = is_array($data['days_of_week']) ? $data['days_of_week'] : [$data['days_of_week']];
                $dayValues = array_map(fn($day) => $day instanceof DayOfWeek ? $day->value : (int)$day, $daysOfWeek);

                // Kiểm tra xung đột thời gian
                $this->checkConflictInstances($roomId, $teacherId, $dayValues, $startTime, $endTime, $startDate, $endDate);

                // ==========================================
                // TẠO TEMPLATE & ĐẨY JOB
                // ==========================================
                $createdTemplates = [];
                // Tạo tạm thời lịch cho 4 tuần sắp tới
                $generateUntil = Carbon::parse($startDate)
                    ->addWeeks(4)->toDateString();
                foreach ($dayValues as $dayValue) {
                    // Tạo lịch cố định
                    $template = $this->templateRepository->create([
                        'class_id' => $class->id,
                        'day_of_week' => $dayValue,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'room_id' => $roomId,
                        'teacher_id' => $teacherId,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'created_by' => Auth::id(),
                    ]);

                    // afterCommit() để chờ transaction hoàn thành
                    GenerateScheduleInstancesJob::dispatch(
                        template: $template,
                        startDate: $startDate,
                        endDate: $generateUntil
                    )->afterCommit();

                    $createdTemplates[] = $template;
                }
                Logging::userActivity(
                    action: 'Tạo lịch cố định',
                    description: "Tạo " . count($createdTemplates) . " lịch cố định cho lớp {$class->name} từ ngày {$startDate}"
                );


                return ServiceReturn::success(data: $createdTemplates);
            },
            useTransaction: true
        );
    }

    /**
     * Tạo lịch học từ lịch cố định (Template) cho lớp học
     * @param ClassScheduleTemplate $template
     * @param string $startDate
     * @param string $endDate
     */
    public function generateInstances(ClassScheduleTemplate $template, string $startDate, string $endDate): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($template, $startDate, $endDate) {
                // Tạo dãy ngày từ startDate đến endDate
                $period = CarbonPeriod::create($startDate, $endDate);

                // Tạo dãy ngày từ startDate đến endDate
                $targetIsoDay = $template->day_of_week instanceof DayOfWeek
                    ? $template->day_of_week->value
                    : (int)$template->day_of_week;

                // Convert dayOfWeek (1=Mon, 7=Sun) to match Carbon's dayOfWeekIso (1=Mon, 7=Sun)
                $instancesToInsert = [];

                // Lấy lớp học của Template
                $class = $template->class;

                $createdBy = $template->created_by;

                // Lấy cấu hình lương của giáo viên trong khoảng thời gian
                $fallbackSalary = (float)($class->teacher_salary_per_session ?? 0);

                // Lấy cấu hình lương của giáo viên trong khoảng thời gian
                $salaryConfigs = $this->teacherSalaryConfigRepository->getConfigsForPeriod(
                    teacherId: $template->teacher_id,
                    classId: $class->id,
                    startDate: $startDate,
                    endDate: $endDate
                );

                foreach ($period as $date) {
                    if ($date->dayOfWeekIso === $targetIsoDay) {
                        $dateStr = $date->toDateString();

                        // Kiểm tra xem ngày học có nằm trong hiệu lực của cấu hình này không
                        $configSalary = $salaryConfigs->first(function ($item) use ($dateStr) {
                            return $item->effective_from <= $dateStr &&
                                ($item->effective_to === null || $item->effective_to >= $dateStr);
                        });

                        // Nếu không có cấu hình lương nào phù hợp, sử dụng lương mặc định
                        $salarySnapshot = $configSalary ? (float)$configSalary->salary_per_session : $fallbackSalary;

                        $instancesToInsert[] = [
                            'class_id' => $class->id,
                            'template_id' => $template->id,
                            'date' => $dateStr,
                            'start_time' => $template->start_time,
                            'end_time' => $template->end_time,
                            'room_id' => $template->room_id,
                            'teacher_id' => $template->teacher_id,
                            'original_teacher_id' => $template->teacher_id,
                            'teacher_salary_snapshot' => $salarySnapshot,
                            'schedule_type' => ScheduleType::Main->value,
                            'fee_type' => FeeType::Normal->value,
                            'status' => ScheduleStatus::Upcoming->value,
                            'created_by' => $createdBy,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                // Lưu lịch học vào cơ sở dữ liệu
                if (!empty($instancesToInsert)) {
                    // Chunking in case of long period
                    foreach (array_chunk($instancesToInsert, 200) as $chunk) {
                        $this->instanceRepository->query()->insert($chunk);
                    }
                }

                return true;
            },
            useTransaction: true
        );
    }

    /**
     * Lấy lịch học theo thời gian và lọc cho Calendar
     * @param Carbon $start
     * @param Carbon $end
     * @param array $filters
     * @return ServiceReturn
     */
    public function getScheduleInstancesCalendar(Carbon $start, Carbon $end,array $filters): ServiceReturn
    {
        return $this->execute(function () use ($start, $end, $filters) {
            $query = $this->instanceRepository->query();
            $query = $this->instanceRepository->getListingQuery($query);

            // Lọc theo thời gian
            $filters['start_date'] = $start->toDateString();
            $filters['end_date'] = $end->toDateString();

            $schedules = $this->instanceRepository->setFilters($query, $filters)->get();

            return $schedules->map(function ($si) {
                $cleanDate = Carbon::parse($si->date)->format('Y-m-d');
                // Logic đổ màu theo loại lịch
                $class = $si->class;
                $teacher = $si->teacher;
                $room = $si->room;
                $subject = $class->subject;
                $teacherColor = $teacher->color ?? Color::Blue['500'];
                // Trạng thái điểm danh lịch học
                $attendanceSession = $si->attendanceSession ?? null;
                if ($attendanceSession) {
                    $labelStatus = $attendanceSession->status->label();
                }else{
                    $labelStatus = 'Chưa điểm danh';
                }
                return EventData::make()
                    ->id($si->id)
                    ->title("\n{$subject->name}\n {$room->name} - {$class->name}")
                    ->start("{$cleanDate}T{$si->start_time}")
                    ->end("{$cleanDate}T{$si->end_time}")
                    ->backgroundColor("transparent")
                    ->borderColor("transparent")
                    ->extendedProps([
                        'teacher' => $teacher->full_name,
                        'teacher_color' => $teacherColor,
                        'teacher_id' => $teacher->id,
                        'start_time' => $si->start_time,
                        'end_time' => $si->end_time,
                        'subject_name' => $subject->name,
                        'class' => $class->name,
                        'room_name' => $room->name,
                        'active_students_count' => $si->active_students_count,
                        'status_attendance_label' => $labelStatus,
                    ])
                    ->borderColor('transparent');
            })->toArray();
        });
    }

    /**
     * Lấy chi tiết của lịch học theo ID
     * @param int $instanceId
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function getDetailInstanceById(int $instanceId): ServiceReturn
    {
        return $this->execute(function () use ($instanceId) {
            $query = $this->instanceRepository->query();
            $instance = $this->instanceRepository
                ->getListingQuery($query)
                ->where('id', $instanceId)
                ->first();
            if (!$instance) {
                throw new ServiceException('Lịch học không tồn tại');
            }
            return $instance;
        });
    }

    /**
     * Cập nhật lịch học
     * @param ScheduleInstance $instance
     * @param array $data
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function updateInstance(ScheduleInstance $instance, array $data)
    {
        return $this->execute(function () use ($instance, $data) {
            $roomId = $data['room_id'] ?? $instance->room_id;
            $teacherId = $data['teacher_id'] ?? $instance->teacher_id;
            $date = $data['date'] ?? $instance->date;
            $dayValues =  [DayOfWeek::from(Carbon::parse($date)->dayOfWeekIso)];
            $startTime = $data['start_time'] ?? $instance->start_time;
            $endTime = $data['end_time'] ?? $instance->end_time;

            // Check conflict lịch học
            $this->checkConflictInstances(
                roomId: $roomId,
                teacherId: $teacherId,
                dayValues: $dayValues,
                startTime: $startTime,
                endTime: $endTime,
                startDate: $date,
                endDate: $date,
                excludeTemplateId: $instance->template_id,
                excludeInstanceId: $instance->id,
            );

            // Cập nhật lịch học
            $instance->update([
                'room_id' => $roomId,
                'teacher_id' => $teacherId,
                'date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]);



            return $instance;
        });
    }

    public function cancelInstance(ScheduleInstance $instance, string $reason)
    {
        return $this->execute(function () use ($instance, $reason) {
            if ($instance->attendanceSession()->exists()) {
                throw new ServiceException("Không thể báo nghỉ: Buổi học này đã có dữ liệu điểm danh.");
            }
            $instance->update([
                'status'        => ScheduleStatus::Cancelled,
                'schedule_type' => ScheduleType::Holiday,
                'note' => $reason,
            ]);
            return $instance;
        });
    }
}
