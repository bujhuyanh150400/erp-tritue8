<?php

namespace App\Services;

use App\Constants\EmployeeStatus;
use App\Constants\UserRole;
use App\Core\DTOs\FilterDTO;
use App\Core\Logs\Logging;
use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Interface\SelectableServiceInterface;
use App\Models\Teacher;
use App\Repositories\TeacherRepository;
use App\Repositories\UserRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class TeacherService extends BaseService implements SelectableServiceInterface
{
    public function __construct(
        protected TeacherRepository $teacherRepository,
        protected UserRepository $userRepository
    ) {}


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
                    'bank_bin' => $data['bank_bin'] ?? null,
                    'bank_account_number' => $data['bank_account_number'] ?? null,
                    'bank_account_holder' => $data['bank_account_holder'] ?? null,
                    'status' => $data['status'],
                    'joined_at' => Carbon::parse($data['joined_at']),
                ]);
                return ServiceReturn::success(
                    data: $teacher,
                    message: 'Tạo hồ sơ giáo viên thành công'
                );
            },
            useTransaction: true
        );
    }

    /**
     * Update giáo viên
     */
    public function updateTeacher(Teacher $teacher, array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($teacher, $data) {

                if (!$teacher) {
                    throw new ServiceException('Giáo viên không tồn tại.');
                }

                $teacherData = [
                    'full_name' => $data['full_name'],
                    'phone' => $data['phone'],
                    'email' => $data['email'] ?? null,
                    'address' => $data['address'] ?? null,
                    'bank_bin' => $data['bank_bin'] ?? null,
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
                    description: 'Cập nhật hồ sơ giáo viên ' . $teacher->full_name
                );

                return ServiceReturn::success($teacher->refresh(), 'Cập nhật giáo viên thành công');
            },
            useTransaction: true
        );
    }


    /**
     * Lấy danh sách giáo viên cho dropdown
     * @param string|null $search
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function getOptions(?string $search = null, array $filters = []): ServiceReturn
    {
        return $this->execute(function () use ($search, $filters) {
            return $this->teacherRepository->query()
                ->when($search, fn($q) => $q->where('full_name', 'ilike', "%{$search}%"))
                ->when(!empty($filters['exclude_id']), fn($q) => $q->where('id', '!=', $filters['exclude_id']))
                ->orderBy('full_name')
                ->where('status', EmployeeStatus::Active)
                ->limit(10)
                ->pluck('full_name', 'id')
                ->toArray();
        });
    }

    /**
     * Lấy tên giáo viên theo id
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
            return $this->teacherRepository->query()
                ->where('id', $id)
                ->value('full_name');
        });
    }

    /**
     * Lấy các chỉ số KPI của giáo viên
     */
    public function getKpiOverview(int $teacherId, string $month): ServiceReturn
    {
        return $this->execute(function () use ($teacherId, $month) {
            $stats = $this->teacherRepository->getKpiStats($teacherId, $month);

            $warnings = [];
            // Cảnh báo: Chưa nộp báo cáo tháng & Đã quá deadline
            if ($stats['has_reports'] && $stats['draft_count'] > 0 && $stats['is_past_deadline']) {
                $warnings[] = [
                    'type' => 'danger',
                    'message' => 'Chưa nộp báo cáo tháng: ' . $stats['draft_count'] . '/' . $stats['total_reports'] . ' báo cáo đang ở trạng thái Nháp (Đã quá deadline)'
                ];
            } elseif ($stats['has_reports'] && $stats['draft_count'] > 0) {
                 $warnings[] = [
                    'type' => 'warning',
                    'message' => 'Còn ' . $stats['draft_count'] . '/' . $stats['total_reports'] . ' báo cáo chưa nộp (Đang ở trạng thái Nháp)'
                ];
            }

            // Cảnh báo: Tỷ lệ chuyên cần < 70%
            if ($stats['attendance_rate'] > 0 && $stats['attendance_rate'] < 70) {
                $warnings[] = [
                    'type' => 'warning',
                    'message' => 'Tỷ lệ chuyên cần trung bình của các lớp thấp (' . $stats['attendance_rate'] . '% < 70%)'
                ];
            }

            return [
                'stats' => $stats,
                'warnings' => $warnings,
            ];
        });
    }

    /**
     * Lấy dữ liệu biểu đồ chuyên cần
     */
    public function getAttendanceChartData(int $teacherId, string $month): ServiceReturn
    {
        return $this->execute(function () use ($teacherId, $month) {
            return $this->teacherRepository->getAttendanceStatsByClass($teacherId, $month);
        });
    }
}
