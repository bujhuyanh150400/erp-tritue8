<?php

namespace App\Services;

use App\Constants\ReportStatus;
use App\Core\Logs\Logging;
use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Repositories\MonthlyReportRepository;

class MonthlyReportService extends BaseService
{
    /**
     * Inject repository phuc vu read/write monthly report.
     */
    public function __construct(
        protected MonthlyReportRepository $monthlyReportRepository,
    ) {}

    /**
     * Admin tao bao cao thay giao vien cho 1 hoc sinh theo lop/thang.
     * Flow:
     * - validate role + input
     * - check hoc sinh thuoc lop trong thang
     * - check unique 1 lop/1 hoc sinh/1 thang
     * - tao monthly_report o trang thai Approved
     * - ghi user activity log
     */
    public function createApprovedReportByAdmin(
        int $studentId,
        int $classId,
        string $month,
        string $content
    ): ServiceReturn {
        return $this->execute(function () use ($studentId, $classId, $month, $content) {
            $actor = auth()->user();
            $actorId = (int) (auth()->id() ?? 0);

            if (! $actorId || ! $actor || ! $actor->isAdmin()) {
                throw new ServiceException('Bạn không có quyền tạo báo cáo thay giáo viên.');
            }

            if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
                throw new ServiceException('Tháng báo cáo không hợp lệ.');
            }

            $content = trim($content);

            if ($content === '') {
                throw new ServiceException('Nội dung báo cáo không được để trống.');
            }

            $class = $this->monthlyReportRepository->findStudentClassInMonth($studentId, $classId, $month);

            if (! $class) {
                throw new ServiceException('Học sinh không thuộc lớp này trong tháng đã chọn.');
            }

            if (! $class->teacher_id) {
                throw new ServiceException('Lớp chưa có giáo viên phụ trách.');
            }

            if ($this->monthlyReportRepository->existsByStudentClassMonth($studentId, $classId, $month)) {
                throw new ServiceException('Lớp này đã có báo cáo trong tháng đã chọn.');
            }

            $now = now();

            $report = $this->monthlyReportRepository->create([
                'teacher_id' => $class->teacher_id,
                'class_id' => $classId,
                'student_id' => $studentId,
                'month' => $month,
                'status' => ReportStatus::Approved,
                'content' => $content,
                'submitted_at' => $now,
                'reviewed_at' => $now,
                'reviewed_by' => $actorId,
                'reject_reason' => null,
            ]);

            Logging::userActivity(
                'create_monthly_report_by_admin',
                "Admin tạo báo cáo tháng {$month} cho học sinh {$studentId}, lớp {$class->name} ({$classId}) và tự động duyệt.",
                $actorId
            );

            return ServiceReturn::success($report, 'Đã tạo báo cáo thay giáo viên và tự động duyệt.');
        }, useTransaction: true);
    }
}
