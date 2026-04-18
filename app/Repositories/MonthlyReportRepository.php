<?php

namespace App\Repositories;

use App\Constants\ReportStatus;
use App\Core\Repository\BaseRepository;
use App\Models\ClassEnrollment;
use App\Models\MonthlyReport;
use App\Models\SchoolClass;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class MonthlyReportRepository extends BaseRepository
{
    /**
     * Dinh nghia model cho repository.
     */
    public function getModel(): string
    {
        return MonthlyReport::class;
    }

    /**
     * Tong hop KPI bao cao thang cua giao vien.
     * Tra ve cac chi so tong, da nop, dung han, da duyet...
     */
    public function getTeacherMonthlyStats(int $teacherId, string $month, CarbonInterface $deadline): array
    {
        $stats = $this->query()
            ->where('teacher_id', $teacherId)
            ->where('month', $month)
            ->selectRaw('
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE status != ?) as submitted_count,
                COUNT(*) FILTER (WHERE status != ? AND submitted_at <= ?) as on_time_count,
                COUNT(*) FILTER (WHERE status = ?) as approved_count,
                COUNT(*) FILTER (WHERE status IN (?, ?)) as reviewed_count,
                COUNT(*) FILTER (WHERE status = ?) as draft_count
            ', [
                ReportStatus::Pending->value,
                ReportStatus::Pending->value, $deadline,
                ReportStatus::Approved->value,
                ReportStatus::Approved->value, ReportStatus::Rejected->value,
                ReportStatus::Pending->value,
            ])
            ->first();

        return [
            'total' => (int) ($stats->total ?? 0),
            'submitted_count' => (int) ($stats->submitted_count ?? 0),
            'on_time_count' => (int) ($stats->on_time_count ?? 0),
            'approved_count' => (int) ($stats->approved_count ?? 0),
            'reviewed_count' => (int) ($stats->reviewed_count ?? 0),
            'draft_count' => (int) ($stats->draft_count ?? 0),
        ];
    }

    /**
     * Lay danh sach dong hien thi bao cao theo tung lop hoc cua hoc sinh trong thang.
     * Neu lop chua co bao cao thi tra ve dong "Chua bao cao".
     */
    public function getStudentMonthlyReportRows(int $studentId, string $month): Collection
    {
        $classes = $this->getStudentClassesByMonth($studentId, $month);
        $classIds = $classes->pluck('id')->all();

        if (empty($classIds)) {
            return collect();
        }

        $reports = $this->query()
            ->where('student_id', $studentId)
            ->where('month', $month)
            ->whereIn('class_id', $classIds)
            ->with([
                'teacher.user',
                'reviewer.teacher',
                'reviewer.staff',
                'reviewer.student',
                'class',
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('class_id')
            ->map(fn(Collection $items) => $items->first());

        return $classes
            ->map(function (SchoolClass $class) use ($reports) {
                /** @var MonthlyReport|null $report */
                $report = $reports->get($class->id);
                $status = $report?->status;

                return [
                    'class_id' => $class->id,
                    'class_name' => $class->name,
                    'month' => $report?->month,
                    'has_report' => $report !== null,
                    'teacher_name' => $report?->teacher?->full_name ?? ($class->teacher?->full_name ?? '-'),
                    'submitted_at' => $report?->submitted_at?->format('d/m/Y H:i') ?? '-',
                    'status_label' => $status?->label() ?? 'Chưa báo cáo',
                    'status_color' => $this->resolveStatusColor($status),
                    'reviewed_at' => $report?->reviewed_at?->format('d/m/Y H:i') ?? '-',
                    'reject_reason' => $report?->reject_reason ?: '-',
                    'content' => $report?->content ?: '-',
                ];
            })
            ->values();
    }

    /**
     * Lay option lop chua co bao cao de admin tao bao cao thay giao vien.
     * Output la mang [class_id => "class_name (GV: ...)"].
     */
    public function getStudentMissingReportClassOptions(int $studentId, string $month): array
    {
        $classes = $this->getStudentClassesByMonth($studentId, $month);
        $existingClassIds = $this->query()
            ->where('student_id', $studentId)
            ->where('month', $month)
            ->pluck('class_id')
            ->all();

        return $classes
            ->reject(fn(SchoolClass $class) => in_array($class->id, $existingClassIds, true))
            ->mapWithKeys(function (SchoolClass $class): array {
                $teacherName = $class->teacher?->full_name ?? 'Chưa phân công giáo viên';

                return [
                    $class->id => "{$class->name} (GV: {$teacherName})",
                ];
            })
            ->all();
    }

    /**
     * Tim 1 lop cua hoc sinh co hieu luc trong thang duoc chon.
     * Dung cho service guard truoc khi tao bao cao.
     */
    public function findStudentClassInMonth(int $studentId, int $classId, string $month): ?SchoolClass
    {
        return $this->getStudentClassesByMonth($studentId, $month)
            ->firstWhere('id', $classId);
    }

    /**
     * Kiem tra da ton tai monthly_report theo student + class + month hay chua.
     */
    public function existsByStudentClassMonth(int $studentId, int $classId, string $month): bool
    {
        return $this->query()
            ->where('student_id', $studentId)
            ->where('class_id', $classId)
            ->where('month', $month)
            ->exists();
    }

    /**
     * Lay danh sach lop hoc cua hoc sinh trong 1 thang theo enrollment overlap.
     * Chi tra ve cac lop con hieu luc trong khoang thang.
     */
    private function getStudentClassesByMonth(int $studentId, string $month): Collection
    {
        [$from, $to] = $this->resolveMonthRange($month);

        return ClassEnrollment::query()
            ->where('student_id', $studentId)
            ->whereDate('enrolled_at', '<=', $to)
            ->where(function ($query) use ($from) {
                $query->whereNull('left_at')
                    ->orWhereDate('left_at', '>=', $from);
            })
            ->with([
                'class.teacher',
            ])
            ->get()
            ->pluck('class')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * Map mau hien thi theo trang thai bao cao.
     */
    private function resolveStatusColor(?ReportStatus $status): string
    {
        return match ($status) {
            ReportStatus::Pending => 'gray',
            ReportStatus::Submitted => 'warning',
            ReportStatus::Approved => 'success',
            ReportStatus::Rejected => 'danger',
            default => 'gray',
        };
    }

    /**
     * Chuyen chuoi YYYY-MM thanh khoang ngay dau thang/cuoi thang.
     * Neu month khong hop le thi fallback thang hien tai.
     */
    private function resolveMonthRange(string $month): array
    {
        try {
            $monthDate = Carbon::createFromFormat('Y-m', $month);
        } catch (Throwable) {
            $monthDate = now();
        }

        return [
            $monthDate->copy()->startOfMonth()->toDateString(),
            $monthDate->copy()->endOfMonth()->toDateString(),
        ];
    }
}
