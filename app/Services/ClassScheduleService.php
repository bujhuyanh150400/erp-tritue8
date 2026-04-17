<?php

namespace App\Services;

use App\Constants\DayOfWeek;
use App\Constants\FeeType;
use App\Constants\ScheduleStatus;
use App\Constants\ScheduleType;
use App\Helpers\Helper;
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
use Illuminate\Support\Collection;
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
        int      $roomId,
        int      $teacherId,
        array    $dayValues,
        string   $startTime,
        string   $endTime,
        string   $startDate,
        string   $endDate,
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
            excludeTemplateId: $excludeTemplateId,
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
                $endDate = $data['end_date'] ? Carbon::parse($data['end_date'])->format('Y-m-d') : Helper::getEndlessDateDefault()->toDateString();
                // Lấy ngày tuần từ dữ liệu
                $daysOfWeek = is_array($data['days_of_week']) ? $data['days_of_week'] : [$data['days_of_week']];
                $dayValues = array_map(fn($day) => $day instanceof DayOfWeek ? $day->value : (int)$day, $daysOfWeek);

                // Kiểm tra xung đột thời gian
                $this->checkConflictInstances(
                    roomId: $roomId,
                    teacherId: $teacherId,
                    dayValues: $dayValues,
                    startTime: $startTime,
                    endTime: $endTime,
                    startDate: $startDate,
                    endDate: $endDate,
                );
                // ==========================================
                // TẠO TEMPLATE & ĐẨY JOB
                // ==========================================
                $createdTemplates = [];
                // Tính toán mốc kết thúc sinh lịch thông minh:
                // Ưu tiên sinh từ (Hôm nay + 4 tuần) để đảm bảo luôn có lịch trong tháng tới
                // Nhưng không được vượt quá end_date của Template (nếu có)
                $fourWeeksFromNow = now()->addWeeks(4)->endOfWeek();
                $templateEndDate = Carbon::parse($endDate);

                // Chọn ngày nhỏ hơn giữa (4 tuần tới) và (Ngày kết thúc template)
                if ($templateEndDate->lt($fourWeeksFromNow)) {
                    $generateUntil = $templateEndDate->toDateString();
                } else {
                    $generateUntil = $fourWeeksFromNow->toDateString();
                }
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
     * Cập nhật lịch cố định (Template)
     * @param ClassScheduleTemplate $template
     * @param array $data
     * @param int|null $excludeInstanceId ID buổi học thực tế không kiểm tra (Áp dụng khi update template từ việc kéo thả buổi học, cần loại trừ chính buổi học đó để tránh xung đột giả)
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function updateTemplate(ClassScheduleTemplate $template, array $data, ?int $excludeInstanceId = null): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($template, $data, $excludeInstanceId) {
                // Fill dữ liệu vào model
                if (!isset($data['end_date'])) {
                    $data['end_date'] = Helper::getEndlessDateDefault()->toDateString();
                }
                $template->fill($data);
                // Kiểm tra nếu model không có sự thay đổi nào (Clean) thì thoát luôn, không cần thiết phải chạy tiếp
                if (!$template->isDirty()) {
                    return $template;
                }

                // Nếu có thay đổi, mới bắt đầu xử lý logic phức tạp
                $roomId = $template->room_id;
                $teacherId = $template->teacher_id;
                $startTime = $template->start_time;
                $endTime = $template->end_time;
                $dayOfWeek = $template->day_of_week;
                $startDate = $template->start_date;
                $endDate = $template->end_date;

                // Lấy ra danh sách các trường bị thay đổi để dùng cho logic phía sau

                // Kiểm tra xung đột thời gian
                $this->checkConflictInstances(
                    roomId: $roomId,
                    teacherId: $teacherId,
                    dayValues: [$dayOfWeek],
                    startTime: $startTime,
                    endTime: $endTime,
                    startDate: $startDate,
                    endDate: $endDate,
                    excludeTemplateId: $template->id,
                );

                // Nếu "Thứ" bị thay đổi, cần dịch chuyển ngày của các buổi học tương ứng theo chênh lệch ngày giữa cũ và mới
                $dayShift = $dayOfWeek->value - $template->day_of_week->value;

                // Cập nhật bản ghi Template gốc
                $template->update([
                    'day_of_week' => $dayOfWeek,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'room_id' => $roomId,
                    'teacher_id' => $teacherId,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]);

                // Cập nhật các buổi học tương ứng
                $futureInstances = $this->instanceRepository->query()
                    ->where('template_id', $template->id)
                    ->where('date', '>=', now()->toDateString()) // Chỉ tính từ hôm nay trở đi
                    ->where('status', ScheduleStatus::Upcoming->value) // Chỉ sửa buổi chưa học
                    ->when(!empty($excludeInstanceId), function ($query) use ($excludeInstanceId) {
                        $query->where('id', '!=', $excludeInstanceId);
                    })
                    ->get();
                foreach ($futureInstances as $instance) {
                    $instanceDate = Carbon::parse($instance->date);

                    // Xóa buổi học nếu nó nằm ngoài khoảng thời gian mới (Nếu bị thu hẹp end_date)
                    if (isset($endDate) && $instanceDate->toDateString() > $endDate) {
                        $instance->delete();
                        continue; // Xóa rồi thì bỏ qua không update nữa
                    }

                    // Dịch chuyển ngày nếu "Thứ" bị thay đổi
                    if ($dayShift !== 0) {
                        $instanceDate->addDays($dayShift);
                    }

                    // Cập nhật đồng loạt Giờ, Phòng, Giáo viên, Ngày mới cho các buổi học tương ứng
                    $instance->update([
                        'date' => $instanceDate->toDateString(),
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'room_id' => $roomId,
                        'teacher_id' => $teacherId,
                        'original_teacher_id' => $teacherId,
                    ]);
                }

                Logging::userActivity(
                    action: 'Cập nhật Lịch cố định',
                    description: "Đã cập nhật Template ID {$template->id} của lớp " . $template->class->name
                );

                return $template;
            },
            useTransaction: true // Vẫn luôn phải có transaction
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
                $targetIsoDay = $template->day_of_week->value;

                // Convert dayOfWeek (1=Mon, 7=Sun) to match Carbon's dayOfWeekIso (1=Mon, 7=Sun)
                $instancesToInsert = [];

                // Lấy lớp học của Template
                $class = $template->class;

                // Lấy người tạo Template
                $createdBy = $template->created_by;

                // Lấy cấu hình lương của giáo viên theo ca
                $salarySnapshot = $this->teacherSalaryConfigRepository->getSalarySession(
                    teacherId: $template->teacher_id,
                );
                // (CHỐNG TRÙNG LẶP):
                // Lấy danh sách các ngày đã được sinh lịch của Template này
                $existingDates = $this->instanceRepository->query()
                    ->where('template_id', $template->id)
                    ->whereBetween('date', [
                        Carbon::make($startDate)->startOfDay()->toDateTimeString(),
                        Carbon::make($endDate)->endOfDay()->toDateTimeString()
                    ])
                    ->pluck('date')
                    ->map(fn($date) => Carbon::parse($date)->toDateString())
                    ->unique()
                    ->toArray();
                // Biến mảng thành Key để check
                $existingDatesLookup = array_flip($existingDates);

                foreach ($period as $date) {
                    // Kiểm tra nếu là đúng với ngày học
                    if ($date->dayOfWeekIso === $targetIsoDay) {
                        $dateString = $date->toDateString();
                        // Bỏ qua nếu lịch ngày này đã tồn tại
                        if (isset($existingDatesLookup[$dateString])) {
                            continue;
                        }

                        $instancesToInsert[] = [
                            'class_id' => $class->id,
                            'template_id' => $template->id,
                            'date' => $dateString,
                            'start_time' => $template->start_time,
                            'end_time' => $template->end_time,
                            'room_id' => $template->room_id,
                            'teacher_id' => $template->teacher_id,
                            'original_teacher_id' => $template->teacher_id,
                            'teacher_salary_snapshot' => $salarySnapshot, // Lương = 0 => Sử dụng lương theo tháng
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
    public function getScheduleInstancesCalendar(Carbon $start, Carbon $end, array $filters): ServiceReturn
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
                } else {
                    $labelStatus = 'Chưa điểm danh';
                }
                return EventData::make()
                    ->id((string)$si->id) // Vì lưu vào js, js ko thể lưu số bigint nên chuyển thành chuỗi
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
                        'schedule_type_label' => $si->schedule_type->label(),
                        'schedule_type' => $si->schedule_type,
                    ])
                    ->borderColor('transparent');
            })->toArray();
        });
    }

    /**
     * Cập nhật lại thời gian của lịch học
     * @param int $instanceId
     * @param Carbon $startTime
     * @param Carbon $endTime
     * @return ServiceReturn
     */
    public function editTimeInstance(int $instanceId, Carbon $startTime, Carbon $endTime): ServiceReturn
    {
        return $this->execute(function () use ($instanceId, $startTime, $endTime) {
            // 1. Ràng buộc thời gian cơ bản
            if ($startTime->greaterThanOrEqualTo($endTime)) {
                throw new ServiceException("Giờ kết thúc phải lớn hơn giờ bắt đầu.");
            }

            // 2. Lấy data buổi học
            $instance = $this->instanceRepository->find($instanceId);
            if (!$instance) {
                throw new ServiceException("Không tìm thấy lịch học.");
            }
            if (!$instance->canEditingInstance()) {
                throw new ServiceException("Lịch học này không thể sửa.");
            }

            $date = $instance->date;
            $dayValues = [DayOfWeek::from(Carbon::parse($date)->dayOfWeekIso)];
            $startTimeStr = $startTime->format('H:i:s');
            $endTimeStr = $endTime->format('H:i:s');

            // Kiểm tra xung đột Phòng & Giáo viên (LOẠI TRỪ buổi học hiện tại)
            $this->checkConflictInstances(
                roomId: $instance->room_id,
                teacherId: $instance->teacher_id,
                dayValues: $dayValues,
                startTime: $startTimeStr,
                endTime: $endTimeStr,
                startDate: $date,
                endDate: $date,
                excludeTemplateId: $instance->template_id,
                excludeInstanceId: $instance->id,
            );

            // Cập nhật thời gian
            $instance->update([
                'start_time' => $startTimeStr,
                'end_time' => $endTimeStr,
            ]);

            Logging::userActivity(
                action: 'Cập nhật thời gian buổi học',
                description: "Cập nhật thời gian buổi ngày {$instance->date} lớp {$instance->class_id} thành {$startTime->format('H:i')} - {$endTime->format('H:i')}"
            );

            return $instance;
        });
    }

    /**
     * Di chuyển lịch học
     * @param int $instanceId
     * @param Carbon $newStart
     * @param Carbon $newEnd
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function moveInstance(int $instanceId, Carbon $newStart, Carbon $newEnd): ServiceReturn
    {
        return $this->execute(function () use ($instanceId, $newStart, $newEnd) {
            $instance = $this->instanceRepository->find($instanceId);
            if (!$instance) {
                throw new ServiceException("Không tìm thấy lịch học.");
            }
            if (!$instance->canEditingInstance()) {
                throw new ServiceException("Lịch học này không thể sửa.");
            }

            // 2. Tách Ngày và Giờ từ Carbon object do FullCalendar gửi lên
            $newDate = $newStart->format('Y-m-d');
            $startTimeStr = $newStart->format('H:i:s');
            $endTimeStr = $newEnd->format('H:i:s');
            $dayValues = [DayOfWeek::from($newStart->dayOfWeekIso)];

            // 3. Kiểm tra xung đột tại vị trí MỚI (Vẫn phải exclude chính nó)
            $this->checkConflictInstances(
                roomId: $instance->room_id,
                teacherId: $instance->teacher_id,
                dayValues: $dayValues,
                startTime: $startTimeStr,
                endTime: $endTimeStr,
                startDate: $newDate,
                endDate: $newDate,
                excludeTemplateId: $instance->template_id,
                excludeInstanceId: $instance->id,
            );

            // 4. Lưu dữ liệu dời lịch
            return $instance->update([
                'date' => $newDate,
                'start_time' => $startTimeStr,
                'end_time' => $endTimeStr,
            ]);
        });
    }

    /**
     * Di chuyển lịch học và thay đổi template (Áp dụng cho trường hợp muốn thay đổi luôn thời khóa biểu gốc của lớp học)
     * @param int $instanceId
     * @param Carbon $newStart
     * @param Carbon $newEnd
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function moveInstanceAndChangeTemplate(int $instanceId, Carbon $newStart, Carbon $newEnd): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($instanceId, $newStart, $newEnd) {
                $resultChangeInstance = $this->moveInstance(
                    instanceId: $instanceId,
                    newStart: $newStart,
                    newEnd: $newEnd,
                );
                if ($resultChangeInstance->isError()) {
                    throw new ServiceException($resultChangeInstance->getMessage());
                }
                $instance = $resultChangeInstance->getData();
                $template = $instance->template;
                if (!$template) {
                    throw new ServiceException("Buổi học này không có lịch cố định gốc để cập nhật.");
                }
                $resultChangeTemplate = $this->updateTemplate(
                    template: $template,
                    data: [
                        'day_of_week' => DayOfWeek::from($newStart->dayOfWeekIso),
                        'start_time' => $newStart->format('H:i:s'),
                        'end_time' => $newEnd->format('H:i:s'),
                    ],
                    excludeInstanceId: $instance->id,
                );
                if ($resultChangeTemplate->isError()) {
                    throw new ServiceException($resultChangeTemplate->getMessage());
                }
                return $instance;
            },
            useTransaction: true);
    }

    /**
     * Hủy lịch học và tạo lịch bù
     * @param int $instanceId
     * @param Carbon $newStart
     * @param Carbon $newEnd
     * @param string $reason
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function cancelInstanceAndCreateMakeupInstance(int $instanceId, Carbon $newStart, Carbon $newEnd, string $reason): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($instanceId, $newStart, $newEnd, $reason) {
                /**
                 * @var ScheduleInstance|null $instance
                 */
                $instance = $this->instanceRepository->find($instanceId);
                if (!$instance) {
                    throw new ServiceException("Không tìm thấy lịch học.");
                }

                // CHUẨN BỊ DỮ LIỆU TẠO LỊCH BÙ
                // Lấy từ chính buổi học cũ truyền sang
                $makeupData = [
                    'date' => $newStart->format('Y-m-d'),
                    'start_time' => $newStart->format('H:i:s'),
                    'end_time' => $newEnd->format('H:i:s'),
                    'room_id' => $instance->room_id,
                    'teacher_id' => $instance->teacher_id,
                    'fee_type' => $instance->fee_type,
                    'custom_fee_per_session' => $instance->custom_fee_per_session,
                    'custom_salary' => $instance->custom_salary,
                ];

                // HỦY BUỔI HỌC HIỆN TẠI (Báo nghỉ)
                $cancelResult = $this->cancelInstance($instance, $reason);

                if ($cancelResult->isError()) {
                    throw new ServiceException($cancelResult->getMessage());
                }

                // Lấy instance sau khi đã update trạng thái (từ hàm cancel trả về)
                $canceledInstance = $cancelResult->getData();

                // TẠO LỊCH BÙ
                $makeupResult = $this->createMakeupInstance($canceledInstance, $makeupData);

                if ($makeupResult->isError()) {
                    throw new ServiceException($makeupResult->getMessage());
                }

                // Trả về data của buổi học bù mới được tạo
                return $makeupResult->getData();
            },
            useTransaction: true,
        );
    }

    /**
     * Cập nhật lại chi tiết về lịch học
     * @param ScheduleInstance $instance
     * @param array $data
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function updateInstance(ScheduleInstance $instance, array $data): ServiceReturn
    {
        return $this->execute(function () use ($instance, $data) {
            if (!$instance->canEditingInstance()) {
                throw new ServiceException('Lịch học này không thể sửa');
            }
            $roomId = $data['room_id'] ?? $instance->room_id;
            $teacherId = $data['teacher_id'] ?? $instance->original_teacher_id;
            $date = $data['date'] ?? $instance->date;
            $dayValues = [DayOfWeek::from(Carbon::parse($date)->dayOfWeekIso)];
            $startTime = $data['start_time'] ?? $instance->start_time;
            $endTime = $data['end_time'] ?? $instance->end_time;
            $feeType = $data['fee_type'] instanceof FeeType ? $data['fee_type'] : FeeType::from($data['fee_type']);
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
            // Dữ liệu cập nhật
            $dataUpdate = [
                'room_id' => $roomId,
                'teacher_id' => $teacherId,
                'date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ];
            // Nếu thay đổi lương custom, cập nhật lương custom
            if (!empty($data['custom_salary'])) {
                $dataUpdate['custom_salary'] = $data['custom_salary'];
            } else {
                // Nếu không có lương custom và thay đổi teacher so với teacher gốc, cập nhật lương teacher dựa theo lương của teacher đó
                if ($teacherId !== $instance->original_teacher_id) {
                    $salaryTeacher = $this->teacherSalaryConfigRepository->getSalarySession(
                        teacherId: $teacherId,
                    );
                    if (!empty($salaryTeacher)) {
                        $dataUpdate['custom_salary'] = $salaryTeacher;
                    }
                } // Nếu  thay đổi teacher giống với teacher gốc, cập nhật lương custom = null để sử dụng lương của teacher gốc
                else if ($teacherId === $instance->original_teacher_id) {
                    $dataUpdate['custom_salary'] = null;
                }
            }

            // Nếu thay đổi phí học, cập nhật phí học dựa theo phí học của lớp đó hoặc phí học tùy chỉnh nếu có
            if ($feeType === FeeType::Free) {
                $dataUpdate['custom_fee_per_session'] = 0;
            } elseif ($feeType === FeeType::Custom) {
                $dataUpdate['custom_fee_per_session'] = $data['custom_fee_per_session'];
            }

            // Cập nhật lịch học
            $instance->update($dataUpdate);

            Logging::userActivity(
                action: 'Cập nhật buổi học',
                description: "Cập nhật buổi ngày {$instance->date} lớp {$instance->class_id}"
            );

            return $instance;
        });
    }

    /**
     * Hủy lịch học
     * @param ScheduleInstance $instance
     * @param string $reason
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function cancelInstance(ScheduleInstance $instance, string $reason): ServiceReturn
    {
        return $this->execute(function () use ($instance, $reason) {
            if ($instance->attendanceSession()->exists()) {
                throw new ServiceException("Không thể báo nghỉ: Buổi học này đã có dữ liệu điểm danh.");
            }
            $instance->update([
                'status' => ScheduleStatus::Cancelled,
                'schedule_type' => ScheduleType::Holiday,
                'note' => $reason,
            ]);
            Logging::userActivity(
                action: 'Hủy buổi học',
                description: "Hủy buổi ngày {$instance->date} lớp {$instance->class_id} | Lý do: {$reason}"
            );
            return $instance;
        });
    }

    /**
     * Tạo lịch bù
     * @param ScheduleInstance $instance
     * @param array $data
     * @return ServiceReturn
     */
    public function createMakeupInstance(ScheduleInstance $oldInstance, array $data): ServiceReturn
    {
        return $this->execute(function () use ($oldInstance, $data) {
            if (!$oldInstance->canMakeMarkupInstance()) {
                throw new ServiceException("Không thể tạo lịch bù: Buổi học này không thể tạo lịch bù.");
            }

            $roomId = $data['room_id'] ?? $oldInstance->room_id;
            $teacherId = $data['teacher_id'] ?? $oldInstance->original_teacher_id;
            $date = $data['date'];
            $dayValues = [DayOfWeek::from(Carbon::parse($date)->dayOfWeekIso)];
            $feeType = $data['fee_type'] instanceof FeeType ? $data['fee_type'] : FeeType::from($data['fee_type']);

            // Kiểm tra xung đột (makeup instance không được trùng với instance gốc)
            $this->checkConflictInstances(
                roomId: $roomId,
                teacherId: $teacherId,
                dayValues: $dayValues,
                startTime: $data['start_time'],
                endTime: $data['end_time'],
                startDate: $date,
                endDate: $date,
            );
            // Lấy lớp học
            $class = $oldInstance->class;

            if ($feeType === FeeType::Free) {
                $customFee = 0;
            } elseif ($feeType === FeeType::Custom) {
                $customFee = $data['custom_fee_per_session'];
            } else {
                $customFee = null;
            }

            // Lấy lương hiệu lực của giáo viên trong khoảng thời gian
            // Nếu có lương custom thì sử dụng lương custom
            if (!empty($data['custom_salary'])) {
                $salarySnapshot = (float)$data['custom_salary'];
            } else {
                // Nếu không có lương custom thì sử dụng lương mặc định của lớp học hoặc lương của giáo viên nếu có
                $salarySnapshot = $this->teacherSalaryConfigRepository->getSalarySession(
                    teacherId: $teacherId,
                );
            }


            // Insert vào Database
            return $this->instanceRepository->create([
                'class_id' => $oldInstance->class_id,
                'date' => $date,
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'fee_type' => $data['fee_type'] ?? FeeType::Normal->value,
                'room_id' => $roomId,
                'teacher_id' => $teacherId,
                'original_teacher_id' => $class->teacher_id ?? $teacherId, // Lưu vết GV gốc
                'schedule_type' => ScheduleType::Makeup,
                'status' => ScheduleStatus::Upcoming,
                'teacher_salary_snapshot' => $salarySnapshot,
                'linked_makeup_for' => $oldInstance->id,
                'custom_salary' => $data['custom_salary'] ?? null,
                'custom_fee_per_session' => $customFee,
                'created_by' => Auth::id(),
            ]);
        });
    }

    /**
     * Tạo lịch học bù
     * @param array $data
     * @param Collection $records
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function createExtraSchedule(array $data, Collection $records): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($data, $records) {
                if ($records->isEmpty()) {
                    throw new ServiceException("Danh sách học sinh không thể rỗng.");
                }
                $roomId = $data['room_id'];
                $teacherId = $data['teacher_id'];
                $date = $data['date'];
                $dayValues = [DayOfWeek::from(Carbon::parse($date)->dayOfWeekIso)];
                $feeType = $data['fee_type'] instanceof FeeType ? $data['fee_type'] : FeeType::from($data['fee_type']);
                $startTime = $data['start_time'];
                $endTime = $data['end_time'];

                // Kiểm tra xung đột (makeup instance không được trùng với instance gốc)
                $this->checkConflictInstances(
                    roomId: $roomId,
                    teacherId: $teacherId,
                    dayValues: $dayValues,
                    startTime: $startTime,
                    endTime: $endTime,
                    startDate: $date,
                    endDate: $date,
                );


                if ($feeType === FeeType::Free) {
                    $fee = 0;
                } else {
                    $fee = $data['custom_fee_per_session'] ?? null;
                }
                if ($fee === null) {
                    throw new ServiceException("Học phí tăng cường chỉ có thể miễn phí hoặc phải có phí.");
                }

                // Lấy lương hiệu lực của giáo viên trong khoảng thời gian
                // Nếu có lương custom thì sử dụng lương custom
                if (!empty($data['salary'])) {
                    $salarySnapshot = (float)$data['salary'];
                } else {
                    // Nếu không có lương custom thì sử dụng lương mặc định của lớp học hoặc lương của giáo viên nếu có
                    $salarySnapshot = $this->teacherSalaryConfigRepository->getSalarySession(
                        teacherId: $teacherId,
                    );
                }


                // 1. Tạo bản ghi schedule_instances (class_id = null)
                $instance = $this->instanceRepository->create([
                    'class_id' => null,
                    'date' => $date,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'teacher_id' => $teacherId,
                    'original_teacher_id' => $teacherId, // Lưu vết GV gốc
                    'room_id' => $roomId,
                    'schedule_type' => ScheduleType::Extra->value,
                    'status' => ScheduleStatus::Upcoming->value,
                    'fee_type' => $feeType->value,
                    'teacher_salary_snapshot' => $salarySnapshot,
                    'custom_salary' => $salarySnapshot,
                    'custom_fee_per_session' => $fee,
                    'created_by' => Auth::id(),
                ]);
                $studentIds = $records->pluck('id')->toArray();
                // Dùng sync() để lưu vào bảng schedule_instance_participants
                $instance->participants()->sync($studentIds);

                return $instance;
            }, useTransaction: true);
    }
}
