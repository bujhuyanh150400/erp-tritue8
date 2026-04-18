<?php

namespace App\Services;

use App\Constants\AttendanceStatus;
use App\Constants\DayOfWeek;
use App\Constants\ScheduleType;
use App\Core\Logs\Logging;
use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Models\AttendanceRecord;
use App\Models\Student;
use App\Repositories\AttendanceRecordRepository;
use App\Repositories\MonthlyReportRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class StudentReportService extends BaseService
{
    /**
     * Inject cac repository phuc vu gom du lieu de xuat bao cao thang hoc sinh.
     */
    public function __construct(
        protected AttendanceRecordRepository $attendanceRecordRepository,
        protected MonthlyReportRepository $monthlyReportRepository,
    ) {}

    /**
     * Tao file PDF bao cao thang cua hoc sinh, gom day du du lieu 3 tab tren trang view.
     * Ket qua tra ve ServiceReturn chua doi tuong PDF va ten file de page action download.
     */
    public function exportMonthlyReportPdf(Student $student, string $month): ServiceReturn
    {
        return $this->execute(function () use ($student, $month) {
            $actorId = auth()->id();

            if (! $actorId) {
                throw new ServiceException('Bạn cần đăng nhập để xuất báo cáo.');
            }

            if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
                throw new ServiceException('Tháng báo cáo không hợp lệ.');
            }

            $student->loadMissing(['activeClassEnrollments.class']);

            $stats = $this->attendanceRecordRepository->getStudentMonthlyOverview($student->id, $month);
            $classNames = $this->attendanceRecordRepository->getStudentMonthlyClassNames($student->id, $month)->all();
            $historyRows = $this->buildHistoryRows($student->id, $month);
            $classReports = $this->monthlyReportRepository->getStudentMonthlyReportRows($student->id, $month)->all();

            $monthLabel = Carbon::createFromFormat('Y-m', $month)->format('m/Y');
            $fileName = "bao-cao-thang-hoc-sinh-{$student->full_name}-{$month}.pdf";

            $pdf = Pdf::loadView('pdfs.student-monthly-report', [
                'student' => $student,
                'month' => $month,
                'monthLabel' => $monthLabel,
                'classNames' => $classNames,
                'stats' => $stats,
                'historyRows' => $historyRows,
                'classReports' => $classReports,
                'exportedAt' => now()->format('d/m/Y H:i:s'),
            ])
                ->setOptions([
                    'defaultFont' => 'ReportSans',
                    'isFontSubsettingEnabled' => false,
                ])
                ->setPaper('a4', 'landscape');

            Logging::userActivity(
                'export_student_monthly_report',
                "Xuất báo cáo tháng {$month} cho học sinh {$student->id} ({$student->full_name}).",
                (int) $actorId
            );

            return ServiceReturn::success([
                'pdf' => $pdf,
                'file_name' => $fileName,
            ], 'Xuất báo cáo tháng thành công.');
        });
    }

    /**
     * Chuyen du lieu diem danh/diem theo buoi hoc thanh danh sach dong phuc vu hien thi va xuat PDF.
     */
    private function buildHistoryRows(int $studentId, string $month): array
    {
        $records = $this->attendanceRecordRepository
            ->getStudentMonthlyHistoryQuery($studentId, $month)
            ->get();

        return $records
            ->map(function (AttendanceRecord $record): array {
                $date = $record->session?->session_date;
                $weekday = $date ? (DayOfWeek::tryFrom($date->dayOfWeekIso)?->label() ?? '-') : '-';

                $start = $record->session?->scheduleInstance?->start_time;
                $end = $record->session?->scheduleInstance?->end_time;
                $timeRange = '-';
                if ($start && $end) {
                    $timeRange = Carbon::parse($start)->format('H:i') . ' - ' . Carbon::parse($end)->format('H:i');
                }

                $scheduleType = $record->session?->scheduleInstance?->schedule_type;
                $classInfo = $scheduleType === ScheduleType::Extra
                    ? 'Học tăng cường'
                    : ($record->session?->class?->name ?? '-');

                $scores = $record->scores
                    ->map(fn($score) => [
                        'exam_name' => (string) ($score->exam_name ?: "Bài {$score->exam_slot}"),
                        'score' => (float) $score->score,
                        'max_score' => (float) ($score->max_score ?: 10),
                        'note' => (string) ($score->note ?: ''),
                    ])
                    ->values()
                    ->all();

                $rewardPoints = (int) ($record->session?->rewardPoints?->sum('amount') ?? 0);

                return [
                    'date_weekday' => $date ? $date->format('d/m/Y') . ' - ' . $weekday : '-',
                    'time_range' => $timeRange,
                    'attendance_status' => $record->status?->label() ?? '-',
                    'session_type' => $scheduleType?->label() ?? '-',
                    'class_info' => $classInfo,
                    'private_note' => (string) ($record->private_note ?: '-'),
                    'absent_reason' => in_array($record->status, [AttendanceStatus::Absent, AttendanceStatus::AbsentExcused], true)
                        ? (string) ($record->reason_absent ?: '-')
                        : '-',
                    'scores' => $scores,
                    'reward_points' => $rewardPoints > 0 ? "+{$rewardPoints}" : (string) $rewardPoints,
                ];
            })
            ->values()
            ->all();
    }
}
