<?php

namespace App\Services;

use App\Constants\DayOfWeek;
use App\Constants\FeeType;
use App\Constants\ScheduleStatus;
use App\Constants\ScheduleType;
use App\Core\Helpers\Snowflake;
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
    ): void
    {
        // Kiểm tra trùng PHÒNG trên lịch cố định
        $templateRoom = $this->templateRepository->findRoomConflicts($roomId, $dayValues, $startTime, $endTime, $startDate, $endDate);
        if ($templateRoom) {
            throw new ServiceException("Thứ {$templateRoom->day_of_week->label()}: Phòng đã bị đặt cố định bởi lớp [{$templateRoom->class->name}].");
        }
        // Kiểm tra trùng PHÒNG trên các buổi học thực tế (Instances)
        $instanceRoom = $this->instanceRepository->findRoomConflicts($roomId, $dayValues, $startTime, $endTime, $startDate, $endDate);
        if ($instanceRoom) {
            $dayName = Carbon::parse($instanceRoom->date)->dayOfWeekIso;
            throw new ServiceException("Thứ {$dayName}: Phòng vướng lịch thực tế của lớp [{$instanceRoom->class->name}] ngày " . Carbon::parse($instanceRoom->date)->format('d/m/Y'));
        }
        // Kiểm tra trùng Giáo viên trên lịch cố định
        $templateTeacher = $this->templateRepository->findTeacherConflicts($teacherId, $dayValues, $startTime, $endTime, $startDate, $endDate);
        if ($templateTeacher) {
            throw new ServiceException("Thứ {$templateTeacher->day_of_week->label()}: Giáo viên đang dạy cố định lớp [{$templateTeacher->class->name}].");
        }
        // Kiểm tra trùng Giáo viên trên các buổi học thực tế (Instances)
        $instanceTeacher = $this->instanceRepository->findTeacherConflicts($teacherId, $dayValues, $startTime, $endTime, $startDate, $endDate);
        if ($instanceTeacher) {
            $dayName = Carbon::parse($instanceTeacher->date)->dayOfWeekIso;
            throw new ServiceException("Thứ {$dayName}: Giáo viên vướng lịch thực tế của lớp [{$instanceTeacher->class->name}] ngày " . Carbon::parse($instanceTeacher->date)->format('d/m/Y'));
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
     * Lấy lịch học theo thời gian và lọc
     * @param Carbon $start
     * @param Carbon $end
     * @param array $filters
     * @return ServiceReturn
     */
    public function getScheduleInstancesCalendar(Carbon $start, Carbon $end,array $filters): ServiceReturn
    {
        return $this->execute(function () use ($start, $end, $filters) {
            $schedules = $this->instanceRepository->getScheduleInstancesForCalendar($start, $end, $filters);
            return $schedules->map(function ($si) {
                $cleanDate = Carbon::parse($si->date)->format('Y-m-d');
                // Logic đổ màu theo loại lịch
                $color = $si->schedule_type->color();
                $class = $si->class;
                $teacher = $si->teacher;
                $room = $si->room;
                $subject = $class->subject;

                return EventData::make()
                    ->id($si->id)
                    ->title("Lớp: {$class->name} (GV: {$teacher->full_name}) Phòng: {$room->name} | Sĩ số: {$si->si_so}")
                    ->start("{$cleanDate}T{$si->start_time}")
                    ->end("{$cleanDate}T{$si->end_time}")
                    ->backgroundColor($color)
                    ->extendedProps([
                        'start_time' => $si->start_time,
                        'end_time' => $si->end_time,
                        'subject' => $subject->name,
                        'class' => $class->name,
                        'teacher' => $teacher->full_name,
                        'room' => $room->name,
                        'si_so' => $si->si_so,
                    ])
                    ->borderColor('transparent');
            })->toArray();
        });
    }
}
