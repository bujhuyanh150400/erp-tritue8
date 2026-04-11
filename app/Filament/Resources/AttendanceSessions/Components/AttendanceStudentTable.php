<?php

namespace App\Filament\Resources\AttendanceSessions\Components;

use App\Constants\AttendanceSessionStatus;
use App\Constants\AttendanceStatus;
use App\Filament\Resources\AttendanceSessions\Traits\AttendanceStudentTableActions;
use App\Filament\Resources\Students\StudentResource;
use App\Models\AttendanceSession;
use App\Services\AttendanceService;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Livewire\Component;

class AttendanceStudentTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;
    use AttendanceStudentTableActions;

    protected AttendanceService $attendanceService;

    public AttendanceSession $record;

    public function boot(AttendanceService $service): void
    {
        $this->attendanceService = $service;
    }

    public function mount(): void
    {
        $result = $this->attendanceService->getStudentListForAttendance($this->record);

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
            ->groups([
                Group::make('status')
                    ->label('Trạng thái điểm danh')
                    ->getTitleFromRecordUsing(fn (array $record): string => $record['attendance_status']->label() ?? '--')
            ])
            ->defaultGroup('status')
            ->groupingSettingsHidden()
            ->stackedOnMobile()
            ->columns([
                TextColumn::make('student_name')
                    ->label('Họ và tên học sinh')
                    ->weight('bold')
                    ->color('primary')
                    ->url(fn(array $record): string => StudentResource::getUrl('view', ['record' => $record['student_id']]))
                    ->formatStateUsing(function ($state, array $record) {
                        $html = '<div class="flex flex-col">';
                        $html .= '<span class="font-bold text-primary-600">' . $state . '</span>';

                        if (!empty($record['private_note'])) {
                            $html .= '<div class="text-xs font-normal text-gray-500 whitespace-normal max-w-xs mt-1">📝 ' . $record['private_note'] . '</div>';
                        }

                        $html .= '</div>';
                        return $html;
                    })
                    ->html()
                    ->searchable(),

                ViewColumn::make('total_reward_points')
                    ->label('⭐ Sao')
                    ->view('filament.pages.attendance-sessions.reward-points-column-view'),

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
                // Action nhập điểm cả lớp
                $this->actionBulkSaveScore(),
            ])
            ->recordActions([
                $this->actionAttendance(isBulk: false),
                ActionGroup::make([
                    // Action điểm thưởng
                    $this->actionCustomRewards(),
                    // Action đổi thưởng
                    $this->actionRedeemRewards(),
                    // Nhập điểm
                    $this->actionSaveScore(),
                    // Action ghi chú riêng
                    $this->actionPrivateNote(),
                ])
                    ->visible(fn(): bool => $this->record->isDraft())
                    ->button()
                    ->label("Tác vụ")
            ]);
    }


    public function render()
    {
        return view('filament.pages.attendance-sessions.attendance-student-table');
    }
}
