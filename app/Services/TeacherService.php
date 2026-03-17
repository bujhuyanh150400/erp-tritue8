<?php

namespace App\Services;

use App\Constants\UserRole;
use App\Core\DTOs\FilterDTO;
use App\Core\Logs\Logging;
use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Repositories\TeacherRepository;
use App\Repositories\UserRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class TeacherService extends BaseService
{
    public function __construct(
        protected TeacherRepository $teacherRepository,
        protected UserRepository $userRepository
    ) {}

    /**
     * Lấy danh sách giáo viên
     */
    public function getListTeachers(FilterDTO $dto): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($dto) {
                $teachers = $this->teacherRepository->paginate(
                    filters: $dto->getFilters(),
                    perPage: $dto->getPerPage(),
                    page: $dto->getPage(),
                    orderBy: $dto->getSortBy(),
                    orderDirection: $dto->getDirection()
                );
                return ServiceReturn::success($teachers);
            },
            returnCatchCallback: function () use ($dto) {
                return ServiceReturn::success(
                    data: new LengthAwarePaginator(
                        items: [],
                        total: 0,
                        perPage: $dto->getPerPage(),
                        currentPage: $dto->getPage()
                    )
                );
            }
        );
    }

    /**
     * Tạo giáo viên
     */
    public function createTeacher(array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($data) {

                $user = $this->userRepository->findByUsername($data['user_name']);
                if ($user) {
                    throw new ServiceException('Có tài khoản đã dùng với tên đăng nhập này, vui lòng chọn tên đăng nhập khác.');
                }
                $user = $this->userRepository->query()->create([
                    'username' => $data['user_name'],
                    'password' => Hash::make($data['password']),
                    'role' => UserRole::Teacher,
                    'is_active' => true,
                ]);

                $teacher = $this->teacherRepository->create([
                    'user_id' => $user->id,
                    'full_name' => $data['full_name'],
                    'phone' => $data['phone'],
                    'email' => $data['email'] ?? null,
                    'address' => $data['address'] ?? null,
                    'bank_name' => $data['bank_name'] ?? null,
                    'bank_account_number' => $data['bank_account_number'] ?? null,
                    'bank_account_holder' => $data['bank_account_holder'] ?? null,
                    'status' => $data['status'],
                    'joined_at' => Carbon::parse($data['joined_at']),
                ]);

                Logging::userActivity(
                    action: 'Tạo giáo viên',
                    description: 'Tạo hồ sơ giáo viên '.$teacher->full_name
                );

                return ServiceReturn::success(
                    message: 'Tạo hồ sơ giáo viên thành công'
                );
            },
            useTransaction: true
        );
    }

    /**
     * Lấy giáo viên theo user_id
     */
    public function getTeacherById(int $id): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($id) {

                $teacher = $this->teacherRepository->findTeacherByUserId($id);

                if (!$teacher) {
                    throw new ServiceException('Giáo viên không tồn tại.');
                }

                return $teacher;
            }
        );
    }

    /**
     * Update giáo viên
     */
    public function updateTeacher(int $id, array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($id, $data) {

                $teacher = $this->teacherRepository->findTeacherByUserId($id);

                if (!$teacher) {
                    throw new ServiceException('Giáo viên không tồn tại.');
                }
                $teacherData = [
                    'full_name' => $data['full_name'],
                    'phone' => $data['phone'],
                    'email' => $data['email'] ?? null,
                    'address' => $data['address'] ?? null,
                    'bank_name' => $data['bank_name'] ?? null,
                    'bank_account_number' => $data['bank_account_number'] ?? null,
                    'bank_account_holder' => $data['bank_account_holder'] ?? null,
                    'status' => $data['status'],
                    'joined_at' => Carbon::parse($data['joined_at']),
                    'updated_at' => now(),
                ];

                $updated = $this->teacherRepository->updateById($teacher->id, $teacherData);

                if (!$updated) {
                    throw new ServiceException('Cập nhật giáo viên thất bại.');
                }

                Logging::userActivity(
                    action: 'Cập nhật giáo viên',
                    description: 'Cập nhật hồ sơ giáo viên '.$teacher->full_name
                );

                return ServiceReturn::success($updated, 'Cập nhật giáo viên thành công');
            },
            useTransaction: true
        );
    }

    /**
     * Xóa giáo viên
     */
    public function deleteTeacher(int $id): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($id) {

                $teacher = $this->teacherRepository->findTeacherByUserId($id);

                if (!$teacher) {
                    throw new ServiceException('Giáo viên không tồn tại.');
                }

                $deleted = $this->teacherRepository->deleteById($teacher->id);

                if (!$deleted) {
                    throw new ServiceException('Xóa giáo viên thất bại.');
                }

                Logging::userActivity(
                    action: 'Xóa giáo viên',
                    description: 'Xóa hồ sơ giáo viên '.$teacher->full_name
                );

                return ServiceReturn::success(null, 'Xóa giáo viên thành công');
            },
            useTransaction: true
        );
    }
}
