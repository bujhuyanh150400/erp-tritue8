<?php

namespace App\Filament\Resources\StudentReports\Components;

use App\Constants\AttendanceStatus;
use App\Constants\DayOfWeek;
use App\Constants\ScheduleType;
use App\Models\AttendanceRecord;
use App\Models\Student;
use App\Repositories\AttendanceRecordRepository;
use Carbon\CarbonInterface;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;

class StudentMonthlyReportPanel extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public Student $record;
    public string $selectedMonth = '';

    public function mount(): void
    {
        $this->selectedMonth = (string) request()->query('month', now()->format('Y-m'));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(AttendanceRecordRepository $repository) => $repository->getStudentMonthlyHistoryQuery($this->record->id, $this->selectedMonth))
            ->columns([
                TextColumn::make('session.session_date')
                    ->label('Ngày - thứ')
                    ->state(function (AttendanceRecord $record): string {
                        $date = $record->session?->session_date;

                        if (! $date) {
                            return '-';
                        }

                        return $date->format('d/m/Y') . ' - ' . $this->formatWeekday($date);
                    }),

                TextColumn::make('session.scheduleInstance.start_time')
                    ->label('Giờ học')
                    ->state(function (AttendanceRecord $record): string {
                        $start = $record->session?->scheduleInstance?->start_time;
                        $end = $record->session?->scheduleInstance?->end_time;

                        if (! $start || ! $end) {
                            return '-';
                        }

                        return Carbon::parse($start)->format('H:i') . ' - ' . Carbon::parse($end)->format('H:i');
                    }),

                TextColumn::make('status')
                    ->label('Trạng thái điểm danh')
                    ->badge()
                    ->formatStateUsing(fn(AttendanceStatus $state): string => $state->label())
                    ->color(fn(AttendanceStatus $state): string => $state->colorFilament()),

                TextColumn::make('session.scheduleInstance.schedule_type')
                    ->label('Buổi học')
                    ->badge()
                    ->state(function (AttendanceRecord $record): string {
                        $type = $record->session?->scheduleInstance?->schedule_type;

                        return $type instanceof ScheduleType ? $type->label() : '-';
                    }),

                TextColumn::make('class_info')
                    ->label('Lớp tham gia')
                    ->state(function (AttendanceRecord $record): string {
                        $type = $record->session?->scheduleInstance?->schedule_type;

                        if ($type === ScheduleType::Extra) {
                            return 'Học tăng cường';
                        }

                        return $this->normalizeUtf8((string) ($record->session?->class?->name ?? '-'));
                    }),

                TextColumn::make('private_note')
                    ->label('Ghi chú')
                    ->state(fn(AttendanceRecord $record): string => $this->normalizeUtf8((string) ($record->private_note ?: '-')))
                    ->wrap(),

                TextColumn::make('reason_absent')
                    ->label('Lý do vắng mặt')
                    ->state(function (AttendanceRecord $record): string {
                        return in_array($record->status, [AttendanceStatus::Absent, AttendanceStatus::AbsentExcused], true)
                            ? $this->normalizeUtf8((string) ($record->reason_absent ?: '-'))
                            : '-';
                    })
                    ->wrap(),

                ViewColumn::make('attendance_scores')
                    ->label('Điểm')
                    ->state(function (AttendanceRecord $record): array {
                        return $record->scores
                            ->map(fn($score) => [
                                'exam_name' => $this->normalizeUtf8((string) ($score->exam_name ?: "Bài {$score->exam_slot}")),
                                'score' => (float) $score->score,
                                'max_score' => (float) ($score->max_score ?: 10),
                                'note' => $this->normalizeUtf8((string) ($score->note ?: '')),
                            ])
                            ->values()
                            ->all();
                    })
                    ->view('filament.pages.student-reports.attendance-scores-column-view'),

                TextColumn::make('reward_points')
                    ->label('Điểm thưởng')
                    ->badge()
                    ->state(function (AttendanceRecord $record): string {
                        $points = (int) ($record->session?->rewardPoints?->sum('amount') ?? 0);

                        if ($points > 0) {
                            return '+' . $points;
                        }

                        return (string) $points;
                    })
                    ->color(function (AttendanceRecord $record): string {
                        $points = (int) ($record->session?->rewardPoints?->sum('amount') ?? 0);

                        if ($points > 0) {
                            return 'success';
                        }

                        if ($points < 0) {
                            return 'danger';
                        }

                        return 'gray';
                    }),
            ])
            ->paginated([20, 50, 100])
            ->defaultPaginationPageOption(20)
            ->emptyStateHeading('Chưa có dữ liệu học tập trong tháng đã chọn.')
            ->emptyStateDescription('Dữ liệu được lọc theo tháng bạn đã chọn ở danh sách báo cáo.');
    }

    public function render(): View
    {
        return view('filament.common.view-table');
    }

    private function formatWeekday(CarbonInterface $date): string
    {
        return DayOfWeek::tryFrom($date->dayOfWeekIso)?->label() ?? '-';
    }

    /**
     * Lam sach chuoi UTF-8 truoc khi render de tranh loi JsonResponse cua Livewire.
     */
    private function normalizeUtf8(string $value): string
    {
        $normalized = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        if ($normalized === false) {
            return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        return $normalized;
    }
}
