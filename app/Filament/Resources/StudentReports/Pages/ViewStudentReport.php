<?php

namespace App\Filament\Resources\StudentReports\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\StudentReports\StudentReportResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewStudentReport extends ViewRecord
{
    protected static string $resource = StudentReportResource::class;

    /**
     * Nap quan he can thiet de hien thi thong tin tong quan trong view.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->loadMissing(['activeClassEnrollments.class']);
    }

    /**
     * Cac action header cua trang view:
     * - xuat bao cao thang (PDF)
     * - quay lai trang danh sach
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_monthly_report')
                ->label('Xuất báo cáo tháng')
                ->icon(Heroicon::ArrowDownTray)
                ->color('primary')
                ->url(function (): string {
                    return route('student-reports.monthly-export', [
                        'student' => $this->record,
                        'month' => (string) request()->query('month', now()->format('Y-m')),
                    ]);
                })
                ->openUrlInNewTab(),
            CommonAction::backAction(self::getResource()),
        ];
    }
}
