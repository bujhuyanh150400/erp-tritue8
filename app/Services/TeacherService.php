<?php

namespace App\Services;

use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Repositories\TeacherRepository;
use App\Repositories\UserRepository;
use App\Core\Logs\Logging;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class TeacherService extends BaseService
{
    public function __construct(
        protected TeacherRepository $teacherRepository,
        protected UserRepository $userRepository
    ) {}

    public function createTeacher(array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($data) {

                $user = $this->userRepository->findById($data['user_id']);

                if (!$user) {
                    throw new ServiceException('Người dùng không tồn tại.');
                }
                $now = Carbon::now()->getTimestamp();
                $teacherData = [
                    'user_id' => $data['user_id'],
                    'full_name' => $data['full_name'],
                    'phone' => $data['phone'],
                    'email' => $data['email'] ?? null,
                    'address' => $data['address'] ?? null,
                    'bank_name' => $data['bank_name'] ?? null,
                    'bank_account_number' => $data['bank_account_number'] ?? null,
                    'bank_account_holder' => $data['bank_account_holder'] ?? null,
                    'status' => $data['status'],
                    'joined_at' => Carbon::parse($data['joined_at'])->getTimestamp(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $teacher = $this->teacherRepository->create($teacherData);

                if (!$teacher) {
                    throw new ServiceException('Tạo hồ sơ giáo viên thất bại.');
                }

                Logging::userActivity(
                    userId: $user->id,
                    action: 'Tạo giáo viên',
                    description: 'Tạo hồ sơ giáo viên ' . $teacher->full_name
                );

                return ServiceReturn::success($teacher, 'Tạo hồ sơ giáo viên thành công');
            }
        );
    }

    public function updateTeacher(int $teacherId, array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($teacherId, $data) {

                $teacher = $this->teacherRepository->findById($teacherId);

                if (!$teacher) {
                    throw new ServiceException('Giáo viên không tồn tại.');
                }
                $now = Carbon::now()->getTimestamp();
                $teacherData = [
                    'full_name' => $data['full_name'],
                    'phone' => $data['phone'],
                    'email' => $data['email'] ?? null,
                    'address' => $data['address'] ?? null,
                    'bank_name' => $data['bank_name'] ?? null,
                    'bank_account_number' => $data['bank_account_number'] ?? null,
                    'bank_account_holder' => $data['bank_account_holder'] ?? null,
                    'status' => $data['status'],
                    'joined_at' => Carbon::parse($data['joined_at'])->getTimestamp(),
                    'updated_at' => $now,
                ];

                $updated = $this->teacherRepository->updateById($teacherId, $teacherData);

                if (!$updated) {
                    throw new ServiceException('Cập nhật giáo viên thất bại.');
                }

                Logging::userActivity(
                    userId: auth()->id(),
                    action: 'Cập nhật giáo viên',
                    description: 'Cập nhật hồ sơ giáo viên ' . $teacher->full_name
                );

                return ServiceReturn::success($updated, 'Cập nhật giáo viên thành công');
            }
        );
    }

    public function deleteTeacher(int $teacherId): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($teacherId) {

                $teacher = $this->teacherRepository->findById($teacherId);

                if (!$teacher) {
                    throw new ServiceException('Giáo viên không tồn tại.');
                }

                $deleted = $this->teacherRepository->deleteById($teacher);

                if (!$deleted) {
                    throw new ServiceException('Xóa giáo viên thất bại.');
                }

                Logging::userActivity(
                    userId: auth()->id(),
                    action: 'Xóa giáo viên',
                    description: 'Xóa hồ sơ giáo viên ' . $teacher->full_name
                );

                return ServiceReturn::success(null, 'Xóa giáo viên thành công');
            }
        );
    }

    public function getListTeachers(array $filters): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($filters) {
                $teachers = $this->teacherRepository->getListTeacher(filters: $filters, perPage: $filters['limit'] ?? 10);
                return ServiceReturn::success($teachers, 'Lấy danh sách giáo viên thành công'
                );
            }
        );
    }
}
