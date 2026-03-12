<?php


namespace App\Services;

use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Repositories\StaffRepository;
use App\Repositories\UserRepository;
use App\Core\Logs\Logging;
use Carbon\Carbon;

class StaffService extends BaseService
{
    public function __construct(
        protected StaffRepository $staffRepository,
        protected UserRepository  $userRepository
    )
    {
    }

    public function createStaff(array $data): ServiceReturn
    {
        return $this->execute(function () use ($data) {

            $user = $this->userRepository->findById($data['user_id']);

            if (!$user) {
                throw new ServiceException('Người dùng không tồn tại.');
            }
            $now = Carbon::now()->getTimestamp();
            $staffData = [
                'user_id' => $data['user_id'],
                'full_name' => $data['full_name'],
                'phone' => $data['phone'],
                'role_type' => $data['role_type'],
                'bank_name' => $data['bank_name'] ?? null,
                'bank_account_number' => $data['bank_account_number'] ?? null,
                'bank_account_holder' => $data['bank_account_holder'] ?? null,
                'status' => $data['status'],
                'joined_at' => $data['joined_at'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $staff = $this->staffRepository->create($staffData);

            if (!$staff) {
                throw new ServiceException('Tạo nhân viên thất bại.');
            }

            Logging::userActivity(
                userId: auth()->id(),
                action: 'Tạo nhân viên',
                description: 'Tạo hồ sơ nhân viên ' . $staff->full_name
            );

            return ServiceReturn::success($staff, 'Tạo nhân viên thành công');
        });
    }

    public function updateStaff(int $staffId, array $data): ServiceReturn
    {
        return $this->execute(function () use ($staffId, $data) {

            $staff = $this->staffRepository->findById($staffId);

            if (!$staff) {
                throw new ServiceException('Nhân viên không tồn tại.');
            }
            $now = Carbon::now()->getTimestamp();
            $staffData = [
                'full_name' => $data['full_name'] ?? $staff->full_name,
                'phone' => $data['phone'] ?? $staff->phone,
                'role_type' => $data['role_type'] ?? $staff->role_type,
                'bank_name' => $data['bank_name'] ?? $staff->bank_name,
                'bank_account_number' => $data['bank_account_number'] ?? $staff->bank_account_number,
                'bank_account_holder' => $data['bank_account_holder'] ?? $staff->bank_account_holder,
                'status' => $data['status'] ?? $staff->status,
                'joined_at' => $data['joined_at'] ?? $staff->joined_at,
                'updated_at' => $now,
            ];

            $updated = $this->staffRepository->updateById($staffId, $staffData);

            if (!$updated) {
                throw new ServiceException('Cập nhật nhân viên thất bại.');
            }

            Logging::userActivity(
                userId: auth()->id(),
                action: 'Cập nhật nhân viên',
                description: 'Cập nhật hồ sơ nhân viên ' . $staff->full_name
            );

            return ServiceReturn::success($updated, 'Cập nhật nhân viên thành công');
        });
    }

    public function deleteStaff(int $staffId): ServiceReturn
    {
        return $this->execute(function () use ($staffId) {

            $staff = $this->staffRepository->findById($staffId);

            if (!$staff) {
                throw new ServiceException('Nhân viên không tồn tại.');
            }

            $deleted = $this->staffRepository->deleteById($staff);

            if (!$deleted) {
                throw new ServiceException('Xóa nhân viên thất bại.');
            }

            Logging::userActivity(
                userId: auth()->id(),
                action: 'Xóa nhân viên',
                description: 'Xóa hồ sơ nhân viên ' . $staff->full_name
            );

            return ServiceReturn::success(null, 'Xóa nhân viên thành công');
        });
    }

    public function getListStaff(array $filters): ServiceReturn
    {
        return $this->execute(function () use ($filters) {

            $staff = $this->staffRepository->getListStaff(
                $filters,
                $filters['limit'] ?? 10
            );

            return ServiceReturn::success($staff, 'Lấy danh sách nhân viên thành công');
        });
    }
}
