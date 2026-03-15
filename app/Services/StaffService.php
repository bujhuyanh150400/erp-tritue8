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
            catchCallback: function (\Throwable $e) {
                dd($e);
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

                $user = $this->userRepository->findByUsername($data['user_name']);

                if ($user) {
                    throw new ServiceException(
                        'Có tài khoản đã dùng với tên đăng nhập này, vui lòng chọn tên đăng nhập khác.'
                    );
                }

                $user = $this->userRepository->query()->create([
                    'username' => $data['user_name'],
                    'password' => Hash::make($data['password']),
                    'role' => UserRole::Staff,
                    'is_active' => true,
                ]);

                $staff = $this->staffRepository->create([
                    'user_id' => $user->id,
                    'full_name' => $data['full_name'],
                    'phone' => $data['phone'],
                    'role_type' => $data['role_type'],
                    'bank_name' => $data['bank_name'] ?? null,
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

                $staff = $this->staffRepository->findStaffByUserId($id);

                if (!$staff) {
                    throw new ServiceException('Nhân viên không tồn tại.');
                }

                return $staff;
            }
        );
    }

    public function updateStaff(int $id, array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($id, $data) {

                $staff = $this->staffRepository->findStaffByUserId($id);

                if (!$staff) {
                    throw new ServiceException('Nhân viên không tồn tại.');
                }

                $staffData = [
                    'full_name' => $data['full_name'],
                    'phone' => $data['phone'],
                    'role_type' => $data['role_type'],
                    'bank_name' => $data['bank_name'] ?? null,
                    'bank_account_number' => $data['bank_account_number'] ?? null,
                    'bank_account_holder' => $data['bank_account_holder'] ?? null,
                    'status' => $data['status'],
                    'joined_at' => Carbon::parse($data['joined_at']),
                    'updated_at' => now(),
                ];

                $updated = $this->staffRepository->updateById($staff->id, $staffData);

                if (!$updated) {
                    throw new ServiceException('Cập nhật nhân viên thất bại.');
                }

                Logging::userActivity(
                    action: 'Cập nhật nhân viên',
                    description: 'Cập nhật hồ sơ nhân viên '.$staff->full_name
                );

                return ServiceReturn::success($updated, 'Cập nhật nhân viên thành công');
            },
            useTransaction: true
        );
    }

    public function deleteStaff(int $id): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($id) {

                $staff = $this->staffRepository->findStaffByUserId($id);

                if (!$staff) {
                    throw new ServiceException('Nhân viên không tồn tại.');
                }

                $deleted = $this->staffRepository->deleteById($staff->id);

                if (!$deleted) {
                    throw new ServiceException('Xóa nhân viên thất bại.');
                }

                Logging::userActivity(
                    action: 'Xóa nhân viên',
                    description: 'Xóa hồ sơ nhân viên '.$staff->full_name
                );

                return ServiceReturn::success(null, 'Xóa nhân viên thành công');
            },
            useTransaction: true
        );
    }
}
