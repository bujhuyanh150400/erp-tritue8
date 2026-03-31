<?php

namespace App\Filament\Resources\AttendanceSessions\Components;

use App\Constants\AttendanceStatus;
use App\Filament\Resources\AttendanceSessions\Traits\AttendanceStudentTableActions;
use App\Models\AttendanceSession;
use App\Services\AttendanceService;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Livewire\Component;

class AttendanceStudentTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;
    use AttendanceStudentTableActions;

    public AttendanceSession $record;



    public function mount(AttendanceService $service): void
    {
        $result = $service->getStudentListForAttendance($this->record);

        if ($result->isSuccess()) {
            $studentsData = $result->getData();
            $newList = [];

            foreach ($studentsData as $studentRow) {
                $newList[$studentRow['student_id']] = $studentRow;
            }
            $this->attendanceList = $newList;
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (?string $search) {
                // Biến mảng thành Collection để tận dụng các hàm filter của Laravel
                $collection = collect($this->attendanceList);

                if (filled($search)) {
                    $search = mb_strtolower($search);
                    $collection = $collection->filter(fn($item) => str_contains(mb_strtolower($item['student_name']), $search));
                }

                // Trả về mảng tuần tự (values) cho Filament render
                return $collection->values()->toArray();
            })
            ->columns([
                TextColumn::make('student_name')
                    ->label('Họ và tên học sinh')
                    ->searchable() // Kích hoạt ô tìm kiếm
                    ->weight('bold')
                    ->color('primary')
                    // Hiển thị tổng sao (Data đã được Service tính sẵn qua withSum)
                    ->description(fn(array $record): string => '⭐ Tổng sao hiện tại: ' . ($record['total_reward_points'] ?? 0)),

                TextColumn::make('attendance_status')
                    ->label('Trạng thái')
                    ->badge()
                    ->default(null)
                    ->formatStateUsing(fn(AttendanceStatus $state): string => $state->label() ?? '--')
                    ->color(fn(AttendanceStatus $state): string => $state->colorFilament())
                    ->description(fn(array $record): ?string => match ($record['attendance_status']) {
                        AttendanceStatus::Late => 'Giờ đến: ' . ($record['check_in_time'] ? Carbon::parse($record['check_in_time'])->format('H:i') : '--'),
                        AttendanceStatus::AbsentExcused => 'Lý do: ' . ($record['reason_absent'] ?? '--'),
                        default => null,
                    }),

                ViewColumn::make('attendance_scores')
                    ->label('Kết quả điểm')
                    ->view('filament.pages.attendance-sessions.attendance-scores-column-view'),
            ])
            ->paginated(false)
            ->toolbarActions([
                // Action điểm danh hàng loạt
                $this->actionAttendance(isBulk: true),
            ])
            ->recordActions([
                // Action điểm danh
                $this->actionAttendance(isBulk: false),

                // Nhập điểm
                $this->actionSaveScore(),
            ]);
    }

    public function render()
    {
        return view('filament.pages.attendance-sessions.attendance-student-table');
    }
}
