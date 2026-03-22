<?php

namespace App\Services;

use App\Constants\ClassStatus;
use App\Constants\EmployeeStatus;
use App\Core\Logs\Logging;
use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Models\SchoolClass;
use App\Repositories\ClassRepository;
use App\Repositories\SubjectRepository;
use App\Repositories\TeacherRepository;

class ClassService extends BaseService
{

    public function __construct(
        protected ClassRepository   $classRepository,
        protected SubjectRepository $subjectRepository,
        protected TeacherRepository $teacherRepository
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
                $newMaxStudents = (int) $data['max_students'];
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
}
