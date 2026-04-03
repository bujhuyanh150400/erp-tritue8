<?php

namespace App\Repositories;

use App\Constants\ReportStatus;
use App\Core\Interfaces\FilterFilament;
use App\Core\Repository\BaseRepository;
use App\Models\MonthlyReport;
use Carbon\CarbonInterface;

class MonthlyReportRepository extends BaseRepository
{
    public function getModel(): string
    {
        return MonthlyReport::class;
    }

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
}
