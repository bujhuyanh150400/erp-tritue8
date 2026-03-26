<?php

namespace App\Services;

use App\Constants\DayOfWeek;
use App\Constants\FeeType;
use App\Constants\ScheduleStatus;
use App\Constants\ScheduleType;
use App\Core\Logs\Logging;
use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Models\ClassScheduleTemplate;
use App\Models\SchoolClass;
use App\Repositories\ClassScheduleTemplateRepository;
use App\Repositories\TeacherSalaryConfigRepository;
use App\Repositories\ScheduleInstanceRepository;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;

class ClassScheduleService extends BaseService
{
    public function __construct(
        protected ClassScheduleTemplateRepository $templateRepository,
        protected ScheduleInstanceRepository      $instanceRepository,
        protected TeacherSalaryConfigRepository   $salaryConfigRepository
    )
    {
    }

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
                $dayOfWeek = $data['day_of_week'] instanceof DayOfWeek ? $data['day_of_week'] : DayOfWeek::from($data['day_of_week']);
                $startTime = $data['start_time'];
                $endTime = $data['end_time'];
                $startDate = $data['start_date'];

                // Set default end_date to end of year or class end_at if not provided
                $endDate = $data['end_date'] ?? ($class->end_at ?? Carbon::parse($startDate)->addMonths(6)->toDateString());

                // 1. Kiểm tra trùng phòng
                $roomConflicts = $this->instanceRepository->checkRoomConflictForTemplate(
                    roomId: $roomId,
                    dayOfWeek: $dayOfWeek,
                    startTime: $startTime,
                    endTime: $endTime,
                    startDate: $startDate
                );

                if ($roomConflicts->isNotEmpty()) {
                    $conflict = $roomConflicts->first();
                    $dateFormatted = Carbon::parse($conflict->date)->format('d/m/Y');
                    throw new ServiceException("Phòng đã có lớp [{$conflict->class_name}] từ [{$conflict->start_time}]–[{$conflict->end_time}] vào ngày {$dateFormatted}.");
                }

                // 2. Kiểm tra trùng giáo viên
                $teacherConflicts = $this->instanceRepository->checkTeacherConflictForTemplate(
                    teacherId: $teacherId,
                    dayOfWeek: $dayOfWeek,
                    startTime: $startTime,
                    endTime: $endTime,
                    startDate: $startDate
                );

                if ($teacherConflicts->isNotEmpty()) {
                    $conflict = $teacherConflicts->first();
                    $dateFormatted = Carbon::parse($conflict->date)->format('d/m/Y');
                    throw new ServiceException("Giáo viên đang có lớp [{$conflict->class_name}] từ [{$conflict->start_time}]–[{$conflict->end_time}] vào ngày {$dateFormatted}.");
                }

                // 3. Tạo Template
                $template = $this->templateRepository->create([
                    'class_id' => $class->id,
                    'day_of_week' => $dayOfWeek,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'room_id' => $roomId,
                    'teacher_id' => $teacherId,
                    'start_date' => $startDate,
                    'end_date' => $data['end_date'] ?? null, // Lưu null nếu không có
                    'created_by' => Auth::id(),
                ]);

                // 4. Sinh schedule_instances từ template
                $this->generateInstances($class, $template, $startDate, $endDate);

                // Ghi Log
                Logging::userActivity(
                    action: 'Tạo lịch cố định',
                    description: "Tạo lịch cố định lớp {$class->name} (Mã: {$class->code}) thứ {$dayOfWeek->value} {$startTime}-{$endTime} từ {$startDate}"
                );

                return ServiceReturn::success(data: $template);
            },
            useTransaction: true
        );
    }

    /**
     * Cập nhật lịch cố định (Template)
     * Áp dụng từ một ngày cụ thể
     */
    public function updateTemplate(ClassScheduleTemplate $oldTemplate, array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($oldTemplate, $data) {
                $class = $oldTemplate->class;
                $applyFrom = Carbon::parse($data['apply_from'])->toDateString();

                $roomId = $data['room_id'];
                $teacherId = $data['teacher_id'] ?? $class->teacher_id;
                $dayOfWeek = $data['day_of_week'] instanceof DayOfWeek ? $data['day_of_week'] : DayOfWeek::from($data['day_of_week']);
                $startTime = $data['start_time'];
                $endTime = $data['end_time'];

                // End date default
                $endDate = $data['end_date'] ?? ($class->end_at ?? Carbon::parse($applyFrom)->addMonths(6)->toDateString());

                // 1. Kiểm tra trùng phòng (bỏ qua template cũ đang sửa)
                $roomConflicts = $this->instanceRepository->checkRoomConflictForTemplate(
                    roomId: $roomId,
                    dayOfWeek: $dayOfWeek,
                    startTime: $startTime,
                    endTime: $endTime,
                    startDate: $applyFrom,
                    excludeTemplateId: $oldTemplate->id
                );

                if ($roomConflicts->isNotEmpty()) {
                    $conflict = $roomConflicts->first();
                    $dateFormatted = Carbon::parse($conflict->date)->format('d/m/Y');
                    throw new ServiceException("Phòng đã có lớp [{$conflict->class_name}] từ [{$conflict->start_time}]–[{$conflict->end_time}] vào ngày {$dateFormatted}.");
                }

                // 2. Kiểm tra trùng giáo viên
                $teacherConflicts = $this->instanceRepository->checkTeacherConflictForTemplate(
                    teacherId: $teacherId,
                    dayOfWeek: $dayOfWeek,
                    startTime: $startTime,
                    endTime: $endTime,
                    startDate: $applyFrom,
                    excludeTemplateId: $oldTemplate->id
                );

                if ($teacherConflicts->isNotEmpty()) {
                    $conflict = $teacherConflicts->first();
                    $dateFormatted = Carbon::parse($conflict->date)->format('d/m/Y');
                    throw new ServiceException("Giáo viên đang có lớp [{$conflict->class_name}] từ [{$conflict->start_time}]–[{$conflict->end_time}] vào ngày {$dateFormatted}.");
                }

                // 3. Đóng template cũ
                if ($oldTemplate->end_date === null || Carbon::parse($oldTemplate->end_date)->gt(Carbon::parse($applyFrom)->subDay())) {
                    $oldTemplate->update([
                        'end_date' => Carbon::parse($applyFrom)->subDay()->toDateString()
                    ]);
                }

                // 4. Tạo template mới từ ngày áp dụng
                $newTemplate = $this->templateRepository->create([
                    'class_id' => $class->id,
                    'day_of_week' => $dayOfWeek,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'room_id' => $roomId,
                    'teacher_id' => $teacherId,
                    'start_date' => $applyFrom,
                    'end_date' => $data['end_date'] ?? null,
                    'created_by' => Auth::id(),
                ]);

                // 5. Xóa các schedule_instances tương lai của template cũ (từ applyFrom)
                $this->instanceRepository->query()
                    ->where('class_id', $class->id)
                    ->where('template_id', $oldTemplate->id)    // chỉ xóa buổi của template gốc nếu cần, hoặc xóa toàn bộ buổi chính của lớp này kể từ ngày đó
                    ->where('date', '>=', $applyFrom)
                    ->where('schedule_type', ScheduleType::Main->value)
                    ->where('status', ScheduleStatus::Upcoming->value)
                    ->delete();

                // 6. Sinh schedule_instances từ template mới
                $this->generateInstances($class, $newTemplate, $applyFrom, $endDate);

                // Ghi Log
                Logging::userActivity(
                    action: 'Cập nhật lịch cố định',
                    description: "Đổi lịch cố định lớp {$class->name} từ ngày {$applyFrom}"
                );

                return ServiceReturn::success(data: $newTemplate);
            },
            useTransaction: true
        );
    }

    /**
     * Sinh tự động các instance từ Template
     */
    protected function generateInstances(SchoolClass $class, ClassScheduleTemplate $template, string $startDate, string $endDate): void
    {
        // 1. Tạo dãy ngày từ startDate đến endDate
        $period = CarbonPeriod::create($startDate, $endDate);

        // Convert dayOfWeek (1=Mon, 7=Sun) to match Carbon's dayOfWeekIso (1=Mon, 7=Sun)
        $targetIsoDay = (int)$template->day_of_week->value;

        $instancesToInsert = [];
        $createdBy = Auth::id();

        foreach ($period as $date) {
            if ($date->dayOfWeekIso === $targetIsoDay) {
                $dateStr = $date->toDateString();

                // Evaluate salary for this specific date
                $salarySnapshot = $this->getTeacherSalary($template->teacher_id, $class->id, $dateStr, $class->teacher_salary_per_session);

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
                    'fee_type' => FeeType::Normal,
                    'status' => ScheduleStatus::Upcoming->value,
                    'created_by' => $createdBy,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($instancesToInsert)) {
            // Chunking in case of long period
            foreach (array_chunk($instancesToInsert, 200) as $chunk) {
                $this->instanceRepository->query()->insert($chunk);
            }
        }
    }

    /**
     * Find effective salary snapshot for a date
     */
    protected function getTeacherSalary(int $teacherId, int $classId, string $date, ?float $fallbackSalary): float
    {
        return $this->salaryConfigRepository->getEffectiveSalary($teacherId, $classId, $date, $fallbackSalary);
    }
}
