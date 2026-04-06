<?php

namespace App\Filament\Resources\Teachers\Widgets;

use App\Models\Teacher;
use App\Services\TeacherService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

class TeacherKpiStatsOverview extends BaseWidget
{
    #[Reactive]
    public ?Teacher $record = null;

    #[Reactive]
    public string $selectedMonth = '';

    protected function getStats(): array
    {
        if (!$this->record) {
            return [];
        }

        $service = app(TeacherService::class);
        $result = $service->getKpiOverview($this->record->id, $this->selectedMonth);
        $data = $result->getData();
        $stats = $data['stats'] ?? [];

        return [
            Stat::make('Tổng số lớp đang dạy', $stats['total_active_classes'] ?? 0)
                ->description('Trạng thái: Đang hoạt động')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary'),

            Stat::make('Tổng buổi dạy tháng', $stats['total_sessions'] ?? 0)
                ->description('Buổi đã hoàn thành trong tháng này: ' .  $stats['total_sessions'])
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('warning'),

            Stat::make('Tỷ lệ chuyên cần TB', ($stats['attendance_rate'] ?? 0) . '%')
                ->description('Trung bình: ' . $stats['attendance_rate'] . '%')
                ->descriptionIcon('heroicon-m-user-group')
                ->color($stats['attendance_rate'] < 70 ? 'danger' : 'success'),

            Stat::make('Tỷ lệ nộp báo cáo đúng hạn', ($stats['submission_rate'] ?? 0) . '%')
                ->description('Trung bình: ' . $stats['submission_rate'] . '%')
                ->descriptionIcon('heroicon-m-document-check')
                ->color($stats['submission_rate'] < 100 ? 'warning' : 'success'),

            Stat::make('Tỷ lệ báo cáo được duyệt ngay', ($stats['approval_rate'] ?? 0) . '%')
                ->description('Tỷ lệ: ' . $stats['approval_rate'] . '%')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('info'),

            Stat::make('Điểm TB toàn bộ lớp', $stats['avg_score'] ?? 0)
                ->description('Điểm TB toàn bộ lớp: ' . $stats['avg_score'] . ' điểm')
                ->descriptionIcon('heroicon-m-star')
                ->color('warning'),
        ];
    }
}
