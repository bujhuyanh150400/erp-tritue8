<?php

namespace App\Services;

use App\Constants\ClassStatus;
use App\Constants\EmployeeStatus;
use App\Core\Logs\Logging;
use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Interface\SelectableServiceInterface;
use App\Models\SchoolClass;
use App\Repositories\ClassEnrollmentRepository;
use App\Repositories\ClassRepository;
use App\Repositories\ScheduleInstanceRepository;
use App\Repositories\SubjectRepository;
use App\Repositories\TeacherRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ClassService extends BaseService implements SelectableServiceInterface
{

    public function __construct(
        protected ClassRepository           $classRepository,
        protected SubjectRepository         $subjectRepository,
        protected TeacherRepository         $teacherRepository,
        protected ClassEnrollmentRepository $classEnrollmentRepository,
        protected ScheduleInstanceRepository $scheduleInstanceRepository,
    )
    {

    }

    /**
     * Tạo lớp học
     * @param array $data
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function createClass(array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($data) {
                $exists = $this->classRepository->query()
                    ->where('code', $data['code'])
                    ->exists();
                // Kiểm tra trùng mã lớp
                if ($exists) {
                    throw new ServiceException("Mã lớp '{$data['code']}' đã tồn tại. Vui lòng đặt mã khác.");
                }
                // Kiểm tra Môn học có đang Active không
                $subjectExists = $this->subjectRepository->query()
                    ->where('id', $data['subject_id'])
                    ->where('is_active', true)
                    ->exists();
                if (!$subjectExists) {
                    throw new ServiceException('Môn học này đã bị khóa hoặc không tồn tại.');
                }

                // Kiểm tra Giáo viên có đang Active không
                $teacherExists = $this->teacherRepository->query()
                    ->where('id', $data['teacher_id'])
                    ->where('status', EmployeeStatus::Active)
                    ->exists();
                if (!$teacherExists) {
                    throw new ServiceException('Giáo viên này không ở trạng thái Đang hoạt động.');
                }

                // 4. Tạo Lớp học (Mặc định status = Active)
                $class = $this->classRepository->create([
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'subject_id' => $data['subject_id'],
                    'teacher_id' => $data['teacher_id'],
                    'grade_level' => $data['grade_level'],
                    'base_fee_per_session' => $data['base_fee_per_session'],
                    'teacher_salary_per_session' => $data['teacher_salary_per_session'],
                    'max_students' => $data['max_students'],
                    'start_at' => $data['start_at'],
                    'end_at' => $data['end_at'] ?? null,
                    'status' => ClassStatus::Active, // Mặc định là Active
                ]);

                // Ghi Log
                Logging::userActivity(
                    action: 'Tạo lớp học',
                    description: "Tạo mới lớp học: {$class->name} (Mã: {$class->code})"
                );

                return ServiceReturn::success(data: $class);
            },
            useTransaction: true
        );
    }

    /**
     * Cập nhật lớp học
     * @param SchoolClass $schoolClass
     * @param array $data
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function updateClass(SchoolClass $schoolClass, array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($schoolClass, $data) {

                // Kiểm tra đổi Môn học
                // Chỉ kiểm tra nếu Admin cố tình gửi ID môn học mới lên
                if (isset($data['subject_id']) && $data['subject_id'] != $schoolClass->subject_id) {
                    if ($schoolClass->scheduleInstances()->exists()) {
                        throw new ServiceException('Không thể đổi môn học vì lớp này đã bắt đầu có các buổi học.');
                    }
                }

                // Kiểm tra Sĩ số tối đa
                // Đếm số học sinh đang học (left_at IS NULL)
                $activeStudentsCount = $schoolClass->enrollments()->whereNull('left_at')->count();
                $newMaxStudents = (int)$data['max_students'];
                if ($newMaxStudents < $activeStudentsCount) {
                    throw new ServiceException("Sĩ số tối đa ({$newMaxStudents}) không thể nhỏ hơn số học sinh hiện tại đang học trong lớp ({$activeStudentsCount} học sinh).");
                }

                // Thực hiện Cập nhật
                // Loại bỏ code, start_at và teacher_id khỏi mảng data (đề phòng update nhầm nếu lọt qua Form)
                unset($data['code'], $data['start_at'], $data['teacher_id']);

                $schoolClass->update($data);

                // Ghi Log
                Logging::userActivity(
                    action: 'Cập nhật lớp học',
                    description: "Cập nhật thông tin lớp học: {$schoolClass->name} (Mã: {$schoolClass->code})"
                );

                return ServiceReturn::success(data: $schoolClass);
            },
            useTransaction: true
        );
    }

    /**
     * Lấy lớp học theo ID
     * @param int $id
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function findClassById(int $id): ServiceReturn
    {
       return $this->execute(
            callback: function () use ($id) {
                $class = $this->classRepository->query()
                    ->where('id', $id)
                    ->first();
                if (!$class) {
                    throw new ServiceException("Lớp học không tồn tại.");
                }
                return $class;
            }
        );
    }

    /**
     * Thêm nhiều học sinh vào lớp học
     * @param SchoolClass $schoolClass
     * @param Collection $students - Danh sách học sinh cần thêm
     * @param array $data
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function addStudentsToClassroom(
        SchoolClass $schoolClass,
        Collection $students,
        array $data
    ): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($schoolClass, $students, $data) {
                // Chuyển đổi sang đối tượng Carbon
                $enrolledAt = Carbon::parse($data['enrolled_at']);
                $classStartAt = Carbon::parse($schoolClass->start_at);

                // Kiểm tra: Nếu ngày nhập học TRƯỚC ngày khai giảng
                if ($enrolledAt->lessThan($classStartAt)) {
                    throw new ServiceException("Ngày bắt đầu học không thể trước ngày khai giảng lớp học ({$classStartAt->format('d/m/Y')}).");
                }
                // Lớp phải đang Active
                if ($schoolClass->status !== ClassStatus::Active) {
                    throw new ServiceException("Không thể thêm học sinh vào lớp đang tạm ngưng hoặc đã kết thúc.");
                }

                // Kiểm tra sĩ số tối đa
                $currentStudents = $this->classEnrollmentRepository->getClassEnrollment($schoolClass->id);
                if ($currentStudents + $students->count() >= $schoolClass->max_students) {
                    throw new ServiceException("Lớp đã đạt sĩ số tối đa ({$currentStudents}/{$schoolClass->max_students} học sinh). Không thể thêm học sinh mới.");
                }
                foreach ($students as $student) {

                    // Kiểm tra xem học sinh này đã đăng ký trong lớp chưa
                    $isAlreadyEnrolled = $this->classEnrollmentRepository->checkStudentIsEnrolledInClass(
                        classId: $schoolClass->id,
                        studentId: $student->id
                    );
                    if ($isAlreadyEnrolled) {
                        throw new ServiceException("Học sinh {$student->full_name} hiện đang học trong lớp rồi.");
                    }
                    // Kiểm tra xung đột lịch học
                    $hasConflict = $this->scheduleInstanceRepository->checkStudentHasConflict(
                        studentId: $student->id,
                        newClassId: $schoolClass->id,
                        enrolledAt: $data['enrolled_at']
                    );
                    if ($hasConflict) {
                        throw new ServiceException("Học sinh {$student->full_name} có xung đột lịch với lớp mới, vui lòng kiểm tra lại.");
                    }


                    // Xử lý học phí
                    $feePerSession = $data['fee_per_session'] ?? null;
                    $feeEffectiveFrom = $feePerSession !== null ? $data['enrolled_at'] : null;

                    // Insert
                    $this->classEnrollmentRepository->create([
                        'class_id' => $schoolClass->id,
                        'student_id' => $student->id,
                        'enrolled_at' => $data['enrolled_at'],
                        'fee_per_session' => $feePerSession,
                        'fee_effective_from' => $feeEffectiveFrom,
                        'note' => $data['note'] ?? null,
                    ]);

                    // 5. Ghi log
                    Logging::userActivity(
                        action: 'Thêm học sinh vào lớp',
                        description: "Thêm HS ID:{$student->id} vào lớp {$schoolClass->name} từ ngày {$data['enrolled_at']}"
                    );
                }
                return ServiceReturn::success();
            },
            useTransaction: true
        );
    }

    /**
     * Thay đổi giáo viên của lớp học
     * @param SchoolClass $class - Lớp học cần thay đổi giáo viên
     * @param int $newTeacherId - ID của giáo viên mới
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function changeTeacher(SchoolClass $class, int $newTeacherId): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($class, $newTeacherId) {

                // check teacher active
                $newTeacher = $this->teacherRepository->find($newTeacherId);
                if (!$newTeacher || $newTeacher->status !== EmployeeStatus::Active) {
                    throw new ServiceException("Giáo viên mới không tồn tại hoặc không hoạt động.");
                }

                // check trùng giáo viên
                $currentTeacherId = $class->teacher_id;
                if ($currentTeacherId == $newTeacherId) {
                    throw new ServiceException("Giáo viên mới trùng với giáo viên hiện tại.");
                }

                // check trùng lịch (ĐÚNG REPO)
                $conflicts = $this->scheduleInstanceRepository->getTeacherScheduleConflicts($newTeacherId, $class->id);

                if ($conflicts->isNotEmpty()) {
                    $message = "Giáo viên mới bị trùng lịch:\n";

                    foreach ($conflicts as $conflict) {
                        $date = Carbon::parse($conflict->date)->format('d/m/Y');
                        $message .= "- {$date} ({$conflict->start_time} - {$conflict->end_time}) lớp {$conflict->class_name}\n";
                    }

                    throw new ServiceException($message);
                }

                // Update class
                $class->update(['teacher_id' => $newTeacherId]);
                //  Update schedule
                $this->scheduleInstanceRepository->updateTeacherForFutureSchedules($class->id, $newTeacherId);
                // Logg
                $oldTeacher = $this->teacherRepository->find($currentTeacherId);
                Logging::userActivity(
                    action: 'Đổi giáo viên',
                    description: "Đổi GV lớp {$class->name} từ {$oldTeacher?->full_name} sang {$newTeacher->full_name}"
                );

                return ServiceReturn::success();
            },
            useTransaction: true
        );
    }

    /**
     * Thay đổi trạng thái của lớp học
     * @param SchoolClass $class
     * @param ClassStatus $newStatus
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function changeStatusClass(SchoolClass $class, ClassStatus $newStatus): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($class, $newStatus) {
                // Không làm gì nếu trùng trạng thái
                if ($class->status === $newStatus) {
                    return ServiceReturn::success();
                }
                $oldStatus = $class->status;
                //  Cancel schedule
                if (in_array($newStatus, [ClassStatus::Suspended, ClassStatus::Ended])) {
                    $this->scheduleInstanceRepository->cancelFutureSchedulesByClassId($class->id);
                }
                // Xử lý Ended
                if ($newStatus === ClassStatus::Ended) {
                    $endAt = $class->end_at
                        ? Carbon::parse($class->end_at)
                        : Carbon::now();
                    $this->classEnrollmentRepository->endActiveEnrollments($class->id);
                    $this->classRepository->updateStatus($class->id, $newStatus, $endAt);
                } else {
                    $this->classRepository->updateStatus($class->id, $newStatus);
                }
                // Log
                Logging::userActivity(
                    action: 'Đổi trạng thái lớp',
                    description: "Đổi trạng thái lớp {$class->name} từ {$oldStatus->label()} sang {$newStatus->label()}"
                );
                return ServiceReturn::success();
            },
            useTransaction: true
        );
    }

    /**
     * Thiết lập học phí riêng cho từng học sinh
     * @param int $classId - ID của lớp học
     * @param int $studentId - ID của học sinh
     * @param array $data - Dữ liệu cập nhật bao gồm 'fee_effective_from' và 'fee'
     * @return ServiceReturn
     */
    public function updateStudentFee(int $classId, int $studentId, array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($classId, $studentId, $data) {
            // Kiểm tra học sinh có đang học trong lớp hay không
                $enrollment = $this->classEnrollmentRepository->getActiveEnrollment($classId, $studentId);
                if (!$enrollment) {
                    throw new ServiceException("Học sinh không đang học trong lớp này.");
                }

                // Kiểm tra ngày áp dụng học phí mới không trước ngày vào lớp
                $originalEnrollment = $this->classEnrollmentRepository->getOriginalEnrollment($classId, $studentId);
                $enrolledAt = Carbon::parse($originalEnrollment->enrolled_at);
                $feeEffectiveFrom = Carbon::parse($data['fee_effective_from']);

                if ($feeEffectiveFrom->lt($enrolledAt)) {
                    throw new ServiceException("Ngày áp dụng không thể trước ngày vào lớp ({$enrolledAt->format('d/m/Y')}).");
                }

                if ($enrollment->fee_effective_from && $feeEffectiveFrom->lte(Carbon::parse($enrollment->fee_effective_from))) {
                    throw new ServiceException("Ngày áp dụng học phí mới phải sau ngày áp dụng học phí hiện hành ({$enrollment->fee_effective_from}).");
                }

                // 1. UPDATE class_enrollments cũ
                $feeEffectiveTo = clone $feeEffectiveFrom;
                $feeEffectiveTo->subDay();
                $enrollment->update([
                    'fee_effective_to' => $feeEffectiveTo->format('Y-m-d')
                ]);

                // 2. INSERT class_enrollments mới
                $this->classEnrollmentRepository->create([
                    'class_id' => $classId,
                    'student_id' => $studentId,
                    'enrolled_at' => $originalEnrollment->enrolled_at,
                    'fee_per_session' => $data['fee_per_session'],
                    'fee_effective_from' => $feeEffectiveFrom->format('Y-m-d'),
                    'fee_effective_to' => null,
                    'left_at' => null,
                    'note' => $enrollment->note
                ]);

                // 3. Ghi log
                $class = $this->classRepository->find($classId);
                Logging::userActivity(
                    action: 'Cập nhật học phí',
                    description: "Đổi học phí của HS ID:{$studentId} lớp {$class->code} thành " . number_format($data['fee_per_session']) . " từ {$feeEffectiveFrom->format('d/m/Y')}"
                );

                return ServiceReturn::success();
            },
            useTransaction: true
        );
    }

    /**
     * Chuyển lớp cho học sinh
     * @param int $oldClassId - ID của lớp học hiện tại
     * @param int $studentId - ID của học sinh
     * @param array $data - Dữ liệu cập nhật bao gồm 'new_class_id' và 'left_at'
     */
    public function transferStudent(int $oldClassId, int $studentId, array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($oldClassId, $studentId, $data) {
                $oldEnrollment = $this->classEnrollmentRepository->getActiveEnrollment($oldClassId, $studentId);
                if (!$oldEnrollment) {
                    throw new ServiceException("Học sinh không đang học trong lớp này.");
                }

                $newClassId = $data['new_class_id'];
                $leftAt = Carbon::parse($data['left_at']);

                $oldEnrolledAt = Carbon::parse($oldEnrollment->enrolled_at);
                if ($leftAt->lt($oldEnrolledAt)) {
                    throw new ServiceException("Ngày chuyển lớp không thể trước ngày vào lớp ({$oldEnrolledAt->format('d/m/Y')}).");
                }

                $newClass = $this->classRepository->find($newClassId);
                if (!$newClass || $newClass->status !== ClassStatus::Active) {
                    throw new ServiceException("Lớp học mới không tồn tại hoặc không ở trạng thái Đang hoạt động.");
                }

                // Cập nhật lớp cũ (rời đi)
                $oldEnrollment->update([
                    'left_at' => $leftAt->format('Y-m-d'),
                    'note' => ltrim($oldEnrollment->note . "\n[Chuyển sang lớp: {$newClass->code}]")
                ]);

                // Đăng ký lớp mới
                $feePerSession = $data['fee_per_session'] ?? null;
                $this->classEnrollmentRepository->create([
                    'class_id' => $newClassId,
                    'student_id' => $studentId,
                    'enrolled_at' => $leftAt->format('Y-m-d'),
                    'fee_per_session' => $feePerSession,
                    'fee_effective_from' => $feePerSession !== null ? $leftAt->format('Y-m-d') : null,
                    'left_at' => null,
                    'note' => $data['note'] ?? null
                ]);

                // Ghi log
                $oldClass = $this->classRepository->find($oldClassId);
                Logging::userActivity(
                    action: 'Chuyển lớp',
                    description: "Chuyển HS ID:{$studentId} từ lớp {$oldClass->code} sang lớp {$newClass->code} từ {$leftAt->format('d/m/Y')}"
                );

                return ServiceReturn::success();
            },
            useTransaction: true
        );
    }

    /**
     * Cho học sinh nghỉ học trong lớp
     * @param int $classId - ID của lớp học
     * @param int $studentId - ID của học sinh
     * @param array $data - Dữ liệu cập nhật bao gồm 'left_at' và 'note'
     */
    public function removeStudent(int $classId, int $studentId, array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($classId, $studentId, $data) {
                $enrollment = $this->classEnrollmentRepository->getActiveEnrollment($classId, $studentId);
                if (!$enrollment) {
                    throw new ServiceException("Học sinh không đang học trong lớp này.");
                }

                $leftAt = Carbon::parse($data['left_at']);
                $enrolledAt = Carbon::parse($enrollment->enrolled_at);

                if ($leftAt->lt($enrolledAt)) {
                    throw new ServiceException("Ngày nghỉ học không thể trước ngày vào lớp ({$enrolledAt->format('d/m/Y')}).");
                }

                $enrollment->update([
                    'left_at' => $leftAt->format('Y-m-d'),
                    'note' => ltrim($enrollment->note . "\n[Nghỉ học]: " . ($data['note'] ?? ''))
                ]);

                // Ghi log
                $class = $this->classRepository->find($classId);
                Logging::userActivity(
                    action: 'Cho nghỉ học',
                    description: "Cho HS ID:{$studentId} nghỉ học lớp {$class->code} từ {$leftAt->format('d/m/Y')}"
                );

                return ServiceReturn::success();
            },
            useTransaction: true
        );
    }

    /**
     * Lấy danh sách lớp học cho dropdown
     * @param string|null $search
     * @param array $filters
     * @return ServiceReturn
     */
    public function getOptions(?string $search = null, array $filters = []): ServiceReturn
    {
        return $this->execute(function () use ($search, $filters) {
            return $this->classRepository->query()
                ->select(['id', 'name', 'code']) // Select thêm cột code
                ->where('status', ClassStatus::Active)
                ->when(!empty($search), function (Builder $query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'ilike', "%{$search}%")
                            ->orWhere('code', 'ilike', "%{$search}%");
                    });
                })
                ->when(!empty($filters['exclude_id']), function (Builder $query) use ($filters) {
                    $query->where('id', '!=', $filters['exclude_id']);
                })
                ->orderBy('id', 'DESC')
                ->limit(10)
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->id => "{$item->name} - (Mã: {$item->code})"];
                })
                ->toArray();
        });
    }

    /**
     * Lấy tên lớp học theo ID
     * @param mixed $id
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function getLabelOption(mixed $id): ServiceReturn
    {
        return $this->execute(function () use ($id) {
            if (empty($id)) {
                return null;
            }
            $class = $this->classRepository->query()
                ->select(['name', 'code'])
                ->where('id', $id)
                ->first();

            return $class ? "{$class->name} - {$class->code}" : null;
        });
    }
}
