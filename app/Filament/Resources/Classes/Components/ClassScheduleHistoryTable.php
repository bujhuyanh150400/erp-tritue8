<?php

namespace App\Filament\Resources\Classes\Components;

use App\Constants\AttendanceStatus;
use App\Constants\ScheduleStatus;
use App\Constants\ScheduleType;
use App\Models\ScheduleInstance;
use App\Models\SchoolClass;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ClassScheduleHistoryTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public SchoolClass $record;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ScheduleInstance::query()
                    ->where('class_id', $this->record->id)
                    ->with(['room', 'teacher', 'attendanceSession'])
                    ->orderBy('date', 'desc')
            )
            ->columns([
                TextColumn::make('date')
                    ->label('Ngày học')
                    ->date('d/m/Y')
                    ->description(fn (ScheduleInstance $record) => $record->start_time . ' - ' . $record->end_time)
                    ->sortable(),

                TextColumn::make('schedule_type')
                    ->label('Loại')
                    ->badge()
                    ->formatStateUsing(fn (ScheduleType $state) => $state->label()),

                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (ScheduleStatus $state) => $state->label())
                    ->color(fn (ScheduleStatus $state) => match ($state) {
                         ScheduleStatus::Upcoming => 'info',
                        ScheduleStatus::Cancelled => 'danger',
                        ScheduleStatus::Completed => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('room.name')
                    ->label('Phòng')
                    ->placeholder('Chưa chọn phòng'),

                TextColumn::make('teacher.full_name')
                    ->label('Giáo viên')
                    ->placeholder('Chưa chọn GV'),

                TextColumn::make('attendanceSession.general_note')
                    ->label('Ghi chú')
                    ->placeholder('---')
                    ->wrap(),

                TextColumn::make('attendance_count')
                    ->label('Số HS có mặt')
                    ->state(function (ScheduleInstance $record) {
                        if (!$record->attendanceSession) return 0;

                        return $record->attendanceSession->attendanceRecords()
                            ->whereIn('status', [
                                AttendanceStatus::Present->value,
                                AttendanceStatus::Late->value,
                            ])
                            ->count();
                    })
                    ->badge()
                    ->color('success')
                    ->alignCenter(),
            ])
            ->recordActions([
                // 1. Xem điểm danh / Bắt đầu điểm danh
                Action::make('view_attendance')
                    ->label(fn (ScheduleInstance $record) => $record->attendanceSession ? 'Xem điểm danh' : 'Bắt đầu điểm danh')
                    ->icon(Heroicon::ClipboardDocumentCheck)
                    ->color('success')
                    ->action(function (ScheduleInstance $record) {
                        if ($record->attendanceSession) {
                            return redirect("/admin/attendance-sessions/{$record->attendanceSession->id}");
                        }
                        return redirect("/admin/attendance-sessions/create?schedule_instance_id={$record->id}");
                    }),

                // 2. Tạo buổi bù
                CreateMakeupSessionAction::make(),

                // 3. Hủy buổi
                CancelScheduleInstanceAction::make(),

                // 4. Đổi phòng học
                ChangeRoomAction::make(),
            ]);
    }

    public function render(): View
    {
        return view('filament.pages.classes.class-schedule-history-table');
    }
}
