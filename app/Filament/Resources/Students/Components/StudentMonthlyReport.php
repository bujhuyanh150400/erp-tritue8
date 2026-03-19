<?php

namespace App\Filament\Resources\Students\Components;

use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class StudentMonthlyReport extends Component
{
    public Student $record; // Nhận record từ Infolist truyền vào
    public string $selectedMonth;

    public function mount(Student $record)
    {
        $this->record = $record;
        // Mặc định hiển thị tháng hiện tại
        $this->selectedMonth = now()->format('Y-m');
    }

    public function render()
    {
        $monthStart = Carbon::parse($this->selectedMonth)->startOfMonth();
        $monthEnd = Carbon::parse($this->selectedMonth)->endOfMonth();

        // 1. Lấy danh sách môn HS đang học
        $enrollments = DB::table('class_enrollments as ce')
            ->join('classes as c', 'ce.class_id', '=', 'c.id')
            ->join('subjects as s', 'c.subject_id', '=', 's.id')
            ->join('teachers as t', 'c.teacher_id', '=', 't.id')
            ->where('ce.student_id', $this->record->id)
            ->whereNull('ce.left_at')
            ->select('s.name as subject_name', 'c.id as class_id', 'c.name as class_name', 't.full_name as teacher_name')
            ->get();

        $reportData = [];

        foreach ($enrollments as $enr) {
            // 2. Thống kê tháng
            $stats = DB::table('attendance_records as ar')
                ->join('attendance_sessions as sess', 'ar.session_id', '=', 'sess.id')
                ->leftJoin('scores as sc', 'sc.attendance_record_id', '=', 'ar.id')
                ->where('ar.student_id', $this->record->id)
                ->where('sess.class_id', $enr->class_id)
                ->whereBetween('sess.session_date', [$monthStart, $monthEnd])
                ->select(
                    DB::raw('COUNT(ar.id) as tong_buoi'),
                    DB::raw("COUNT(ar.id) FILTER (WHERE ar.status IN ('present', 'late')) as co_mat"),
                    DB::raw('ROUND(AVG(sc.score), 2) as diem_tb')
                )->first();

            // 3. Bảng điểm
            $scores = DB::table('scores as sc')
                ->join('attendance_records as ar', 'sc.attendance_record_id', '=', 'ar.id')
                ->join('attendance_sessions as sess', 'ar.session_id', '=', 'sess.id')
                ->where('ar.student_id', $this->record->id)
                ->where('sess.class_id', $enr->class_id)
                ->whereBetween('sess.session_date', [$monthStart, $monthEnd])
                ->select('sess.session_date', 'sc.exam_name', 'sc.score', 'sc.max_score', 'sc.note')
                ->orderBy('sess.session_date')
                ->get();

            // 4. Nhận xét GV
            $monthlyReview = DB::table('monthly_reports')
                ->where('student_id', $this->record->id)
                ->where('class_id', $enr->class_id)
                ->where('month', $this->selectedMonth)
                ->first();

            $reportData[] = [
                'info' => $enr,
                'stats' => $stats,
                'scores' => $scores,
                'review' => $monthlyReview,
            ];
        }

        return view('filament.pages.student.student-monthly-report', compact('reportData'));
    }
}
