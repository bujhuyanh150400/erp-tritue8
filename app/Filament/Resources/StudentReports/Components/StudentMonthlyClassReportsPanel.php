<?php

namespace App\Filament\Resources\StudentReports\Components;

use App\Filament\Components\CommonNotification;
use App\Models\Student;
use App\Repositories\MonthlyReportRepository;
use App\Services\MonthlyReportService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class StudentMonthlyClassReportsPanel extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public Student $record;
    public string $month = '';

    /**
     * Khoi tao context hoc sinh + thang dang xem de filter du lieu tab 3.
     */
    public function mount(Student $record, ?string $month = null): void
    {
        $this->record = $record;
        $this->month = $month ?: (string) request()->query('month', now()->format('Y-m'));
    }

    /**
     * Dinh nghia table custom cho tab "Bao cao theo lop":
     * - danh sach bao cao theo lop trong thang
     * - action admin tao bao cao thay giao vien
     */
    public function table(Table $table): Table
    {
        return $table
            ->records(function (?string $search): array {
                $rows = app(MonthlyReportRepository::class)
                    ->getStudentMonthlyReportRows($this->record->id, $this->month);

                if (filled($search)) {
                    $keyword = mb_strtolower($search);
                    $rows = $rows->filter(function (array $row) use ($keyword): bool {
                        return str_contains(mb_strtolower((string) ($row['class_name'] ?? '')), $keyword)
                            || str_contains(mb_strtolower((string) ($row['teacher_name'] ?? '')), $keyword)
                            || str_contains(mb_strtolower((string) ($row['status_label'] ?? '')), $keyword);
                    });
                }

                return $rows
                    ->map(fn(array $row): array => $this->normalizeUtf8Array($row))
                    ->values()
                    ->all();
            })
            ->columns([
                TextColumn::make('class_name')
                    ->label('Lớp')
                    ->weight('bold')
                    ->description(fn(array $record): string => 'Tháng: ' . ($record['month'] ?: $this->month)),

                TextColumn::make('teacher_name')
                    ->label('Giáo viên báo cáo')
                    ->state(fn(array $record): string => (string) ($record['teacher_name'] ?? '-')),

                TextColumn::make('submitted_at')
                    ->label('Thời gian nộp')
                    ->state(fn(array $record): string => (string) ($record['submitted_at'] ?? '-')),

                TextColumn::make('status_label')
                    ->label('Trạng thái')
                    ->badge()
                    ->color(fn(array $record): string => (string) ($record['status_color'] ?? 'gray')),

                TextColumn::make('reviewed_at')
                    ->label('Thời gian xem xét')
                    ->state(fn(array $record): string => (string) ($record['reviewed_at'] ?? '-')),

                TextColumn::make('reject_reason')
                    ->label('Lý do từ chối')
                    ->state(fn(array $record): string => (string) ($record['reject_reason'] ?? '-'))
                    ->wrap(),

                TextColumn::make('content')
                    ->label('Nội dung báo cáo')
                    ->state(fn(array $record): string => (string) ($record['content'] ?? '-'))
                    ->wrap(),
            ])
            ->headerActions([
                Action::make('create_report_for_teacher')
                    ->label('Tạo báo cáo thay giáo viên')
                    ->icon(Heroicon::Document)
                    ->color('success')
                    ->visible(fn(): bool => auth()->user()?->isAdmin() ?? false)
                    ->disabled(fn(): bool => empty(
                        app(MonthlyReportRepository::class)
                            ->getStudentMissingReportClassOptions($this->record->id, $this->month)
                    ))
                    ->schema([
                        Select::make('class_id')
                            ->label('Lớp của học sinh')
                            ->required()
                            ->searchable()
                            ->options(fn() => $this->normalizeUtf8Array(
                                app(MonthlyReportRepository::class)
                                    ->getStudentMissingReportClassOptions($this->record->id, $this->month)
                            ))
                            ->validationMessages([
                                'required' => 'Vui lòng chọn lớp.',
                            ]),
                        Textarea::make('content')
                            ->label('Nội dung báo cáo')
                            ->required()
                            ->rows(6)
                            ->validationMessages([
                                'required' => 'Vui lòng nhập nội dung báo cáo.',
                            ]),
                    ])
                    ->modalHeading('Tạo báo cáo thay giáo viên')
                    ->modalDescription('Báo cáo tạo từ admin sẽ tự động duyệt và gán giáo viên của lớp làm người báo cáo.')
                    ->modalSubmitActionLabel('Tạo báo cáo')
                    ->action(function (array $data): void {
                        $result = app(MonthlyReportService::class)->createApprovedReportByAdmin(
                                studentId: $this->record->id,
                                classId: (int) $data['class_id'],
                                month: $this->month,
                                content: (string) $data['content'],
                            );

                        if ($result->isError()) {
                            CommonNotification::error()
                                ->title('Không thể tạo báo cáo')
                                ->body($result->getMessage())
                                ->send();

                            throw new Halt();
                        }

                        CommonNotification::success()
                            ->title('Thành công')
                            ->body($result->getMessage())
                            ->send();

                        $this->resetTable();
                    }),
            ])
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 20, 50])
            ->emptyStateHeading('Chưa có lớp học trong tháng đã chọn.')
            ->emptyStateDescription('Học sinh chưa có lớp đang học trong tháng này.');
    }

    /**
     * Render table qua view dung chung cua he thong.
     */
    public function render(): View
    {
        return view('filament.common.view-table');
    }

    /**
     * Lam sach chuoi UTF-8 de tranh loi JsonResponse khi Livewire serialize payload.
     */
    private function normalizeUtf8(string $value): string
    {
        $normalized = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        if ($normalized === false) {
            return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        return $normalized;
    }

    /**
     * Normalize de quy cho du lieu array tra ve UI.
     */
    private function normalizeUtf8Array(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->normalizeUtf8Array($value);
                continue;
            }

            if (is_string($value)) {
                $data[$key] = $this->normalizeUtf8($value);
            }
        }

        return $data;
    }
}
