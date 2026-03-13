<?php

namespace App\Services;

use App\Core\DTOs\FilterDTO;
use App\Core\Logs\Logging;
use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Repositories\StudentRepository;
use App\Repositories\UserRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class StudentService extends BaseService
{
    public function __construct(
        protected StudentRepository $studentRepository,
        protected UserRepository    $userRepository
    )
    {
    }

    public function createStudent(array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($data) {

                $user = $this->userRepository->findById($data['user_id']);

                if (!$user) {
                    throw new ServiceException('Người dùng không tồn tại.');
                }
                $now = Carbon::now()->getTimestamp();
                $studentData = [
                    'user_id' => Auth::id(),
                    'full_name' => $data['full_name'],
                    'dob' => $data['dob'],
                    'gender' => $data['gender'] ?? null,
                    'grade_level' => $data['grade_level'] ?? null,
                    'parent_name' => $data['parent_name'] ?? null,
                    'parent_phone' => $data['parent_phone'] ?? null,
                    'address' => $data['address'] ?? null,
                    'zalo_id' => $data['zalo_id'] ?? null,
                    'note' => $data['note'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $student = $this->studentRepository->create($studentData);

                if (!$student) {
                    throw new ServiceException(message: 'Tạo hồ sơ học sinh thất bại.');
                }
                Logging::userActivity(
                    action: 'Tạo học sinh',
                    description: 'Tạo hồ sơ học sinh ' . $student->full_name
                );

                return ServiceReturn::success($student, message: 'Tạo hồ sơ học sinh thành công');
            }
        );
    }

    public function updateStudent(int $studentId, array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($studentId, $data) {
                $student = $this->studentRepository->findById($studentId);
                if (!$student) {
                    throw new ServiceException('Học sinh không tồn tại.');
                }
                $now = Carbon::now()->getTimestamp();
                $studentData = [
                    'full_name' => $data['full_name'],
                    'dob' => $data['dob'],
                    'gender' => $data['gender'],
                    'grade_level' => $data['grade_level'],
                    'parent_name' => $data['parent_name'],
                    'parent_phone' => $data['parent_phone'],
                    'address' => $data['address'],
                    'zalo_id' => $data['zalo_id'] ?? null,
                    'note' => $data['note'] ?? null,
                    'updated_at' => $now,
                ];
                $updated = $this->studentRepository->updateById($studentId, $studentData);
                if (!$updated) {
                    throw new ServiceException('Cập nhật học sinh thất bại.');
                }
                Logging::userActivity(
                    action: 'Cập nhật học sinh',
                    description: 'Cập nhật hồ sơ học sinh ' . $student->full_name
                );

                return ServiceReturn::success($updated, 'Cập nhật học sinh thành công');
            }
        );
    }

    public function deleteStudent(int $studentId): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($studentId) {
                $student = $this->studentRepository->findById($studentId);
                if (!$student) {
                    throw new ServiceException('Học sinh không tồn tại.');
                }
                $deleted = $this->studentRepository->deleteById($student);
                if (!$deleted) {
                    throw new ServiceException('Xóa học sinh thất bại.');
                }
                Logging::userActivity(
                    action: 'Xóa học sinh',
                    description: 'Xóa hồ sơ học sinh ' . $student->full_name
                );

                return ServiceReturn::success(null, 'Xóa học sinh thành công');
            }
        );
    }

    public function getListStudents(FilterDTO $dto): ServiceReturn
    {
        return $this->execute(callback: function () use ($dto) {
            $students = $this->studentRepository->paginate(
                filters: $dto->getFilters(),
                perPage: $dto->getPerPage(),
                page: $dto->getPage(),
                orderBy: $dto->getSortBy(),
                orderDirection: $dto->getDirection()
            );
            return ServiceReturn::success($students, 'Lấy danh sách học sinh thành công');
        });
    }
}
