<?php

namespace App\Filament\Resources\StudentReports\Schemas;

use App\Constants\GradeLevel;
use App\Filament\Resources\StudentReports\Components\StudentMonthlyClassReportsPanel;
use App\Filament\Resources\StudentReports\Components\StudentMonthlyReportPanel;
use App\Filament\Resources\StudentReports\Widgets\StudentMonthlyStatsOverview;
use App\Models\Student;
use App\Repositories\AttendanceRecordRepository;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;

class StudentReportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Chi tiết báo cáo học sinh')
                    ->columnSpanFull()
                    ->contained(false)
                    ->persistTabInQueryString()
                    ->tabs([
                        Tabs\Tab::make('Tổng quan')
                            ->icon(Heroicon::InformationCircle)
                            ->iconPosition(IconPosition::Before)
                            ->schema([
                                Section::make('Thông tin học sinh')
                                    ->columns(2)
                                    ->compact()
                                    ->schema([
                                        TextEntry::make('full_name')
                                            ->label('Họ tên học sinh')
                                            ->weight('bold'),
                                        TextEntry::make('user_id')
                                            ->label('Mã học sinh'),
                                        TextEntry::make('dob')
                                            ->label('Ngày sinh')
                                            ->date('d/m/Y'),
                                        TextEntry::make('grade_level')
                                            ->label('Khối')
                                            ->state(fn(Student $record): string => $record->grade_level instanceof GradeLevel ? $record->grade_level->label() : '-')
                                            ->badge(),
                                        TextEntry::make('active_classes')
                                            ->label('Các lớp tham gia trong tháng')
                                            ->columnSpanFull()
                                            ->state(function (Student $record): string {
                                                $month = (string) request()->query('month', now()->format('Y-m'));
                                                $classNames = app(AttendanceRecordRepository::class)
                                                    ->getStudentMonthlyClassNames($record->id, $month);

                                                if ($classNames->isEmpty()) {
                                                    return '-';
                                                }

                                                return $classNames
                                                    ->map(fn(string $name): string => "<span class='fi-badge fi-color-primary inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ring-primary-600/20 bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300'>{$name}</span>")
                                                    ->implode(' ');
                                            })
                                            ->html(),
                                    ]),
                                Section::make('Thống kê theo tháng')
                                    ->compact()
                                    ->description('Tháng đang xem: ' . (string) request()->query('month', now()->format('Y-m')))
                                    ->schema([
                                        Livewire::make(StudentMonthlyStatsOverview::class, [
                                            'month' => (string) request()->query('month', now()->format('Y-m')),
                                        ])->columnSpanFull(),
                                    ]),
                            ]),
                        Tabs\Tab::make('Lịch sử học tập chi tiết')
                            ->icon(Heroicon::CalendarDateRange)
                            ->iconPosition(IconPosition::Before)
                            ->schema([
                                Livewire::make(StudentMonthlyReportPanel::class)
                                    ->columnSpanFull(),
                            ]),
                        Tabs\Tab::make('Báo cáo theo lớp')
                            ->icon(Heroicon::DocumentText)
                            ->iconPosition(IconPosition::Before)
                            ->schema([
                                Livewire::make(StudentMonthlyClassReportsPanel::class, [
                                    'month' => (string) request()->query('month', now()->format('Y-m')),
                                ])->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
