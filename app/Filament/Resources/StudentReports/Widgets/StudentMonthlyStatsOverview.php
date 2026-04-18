<?php

namespace App\Filament\Resources\StudentReports\Widgets;

use App\Models\Student;
use App\Repositories\AttendanceRecordRepository;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StudentMonthlyStatsOverview extends StatsOverviewWidget
{
    public Student $record;
    public string $month = '';

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $month = $this->month ?: (string) request()->query('month', now()->format('Y-m'));
        $stats = app(AttendanceRecordRepository::class)
            ->getStudentMonthlyOverview($this->record->id, $month);

        return [
            Stat::make('Tổng số buổi học', (string) ($stats['total_sessions'] ?? 0))
                ->description('Tổng buổi đã phát sinh trong tháng ' . $month)
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary'),

            Stat::make('Tổng số buổi có mặt', (string) ($stats['total_present'] ?? 0))
                ->description('Bao gồm có mặt và đi muộn')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Tổng số buổi vắng mặt', (string) ($stats['total_absent'] ?? 0))
                ->description('Bao gồm vắng có phép và không phép')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),

            Stat::make('Tỉ lệ tham gia', number_format((float) ($stats['participation_rate'] ?? 0), 2) . '%')
                ->description('Tỉ lệ có mặt trên tổng số buổi học')
                ->descriptionIcon('heroicon-m-chart-bar-square')
                ->color('warning'),

            Stat::make('Điểm trung bình', number_format((float) ($stats['average_score'] ?? 0), 2))
                ->description('Điểm trung bình các đầu điểm trong tháng')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary'),
        ];
    }
}
