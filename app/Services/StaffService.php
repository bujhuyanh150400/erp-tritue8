<?php

namespace App\Services;

use App\Constants\UserRole;
use App\Core\DTOs\FilterDTO;
use App\Core\Logs\Logging;
use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Repositories\StaffRepository;
use App\Repositories\UserRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use App\Models\Staff;

class StaffService extends BaseService
{
    public function __construct(
        protected StaffRepository $staffRepository,
        protected UserRepository $userRepository
    ) {}

    public function getListStaff(FilterDTO $dto): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($dto) {

                $staff = $this->staffRepository->paginate(
                    filters: $dto->getFilters(),
                    perPage: $dto->getPerPage(),
                    page: $dto->getPage(),
                    orderBy: $dto->getSortBy(),
                    orderDirection: $dto->getDirection()
                );

                return ServiceReturn::success($staff);
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

    public function createStaff(array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($data) {

                // check username
                $user = $this->userRepository->findByUsername($data['user_name']);

                if ($user) {
                    throw new ServiceException(
                        'Có tài khoản đã dùng với tên đăng nhập này, vui lòng chọn tên đăng nhập khác.'
                    );
                }

                // create user
                $user = $this->userRepository->query()->create([
                    'username' => $data['user_name'],
                    'password' => Hash::make($data['password']),
                    'role' => UserRole::Staff,
                    'is_active' => true,
                ]);

                // create staff
                $staff = $this->staffRepository->create([
                    'user_id' => $user->id,
                    'full_name' => $data['full_name'],
                    'phone' => $data['phone'],
                    'role_type' => $data['role_type'],
                    'bank_bin' => $data['bank_bin'] ?? null,
                    'bank_account_number' => $data['bank_account_number'] ?? null,
                    'bank_account_holder' => $data['bank_account_holder'] ?? null,
                    'status' => $data['status'],
                    'joined_at' => Carbon::parse($data['joined_at']),
                ]);

                if (!$staff) {
                    throw new ServiceException('Tạo nhân viên thất bại.');
                }

                Logging::userActivity(
                    userId: auth()->id(),
                    action: 'Tạo nhân viên',
                    description: 'Tạo hồ sơ nhân viên '.$staff->full_name
                );

                return ServiceReturn::success(
                    data: $staff,
                    message: 'Tạo nhân viên thành công'
                );
            },
            useTransaction: true
        );
    }

    public function getStaffById(int $id): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($id) {

                $staff = $this->staffRepository->findById($id);

                if (!$staff) {
                    throw new ServiceException('Nhân viên không tồn tại.');
                }

                return ServiceReturn::success($staff);
            }
        );
    }

    public function updateStaff(Staff $staff, array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($staff, $data) {

                if (!$staff) {
                    throw new ServiceException('Giáo viên không tồn tại.');
                }

                $staffData = [
                    'full_name' => $data['full_name'],
                    'phone' => $data['phone'],
                    'role_type' => $data['role_type'],
                    'bank_bin' => $data['bank_bin'] ?? null,
                    'bank_account_number' => $data['bank_account_number'] ?? null,
                    'bank_account_holder' => $data['bank_account_holder'] ?? null,
                    'status' => $data['status'],
                    'joined_at' => Carbon::parse($data['joined_at']),
                ];

                $this->staffRepository->updateById($staff->id, $staffData);

                Logging::userActivity(
                    action: 'Cập nhật nhân viên',
                    description: 'Cập nhật hồ sơ nhân viên '.$staff->full_name
                );

                return ServiceReturn::success(
                    data: $staff->refresh(),
                    message: 'Cập nhật nhân viên thành công'
                );
            },
            useTransaction: true
        );
    }

    public function deleteStaff(int $id): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($id) {

                $staff = $this->staffRepository->findById($id);

                if (!$staff) {
                    throw new ServiceException('Nhân viên không tồn tại.');
                }

                // delete staff
                $this->staffRepository->deleteById($staff->id);

                $this->userRepository->deleteById($staff->user_id);

                Logging::userActivity(
                    action: 'Xóa nhân viên',
                    description: 'Xóa hồ sơ nhân viên '.$staff->full_name
                );

                return ServiceReturn::success(
                    message: 'Xóa nhân viên thành công'
                );
            },
            useTransaction: true
        );
    }
}
