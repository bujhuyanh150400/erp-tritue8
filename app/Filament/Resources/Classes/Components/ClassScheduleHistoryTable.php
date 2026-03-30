<?php

namespace App\Filament\Resources\Classes\Components;

use App\Constants\AttendanceStatus;
use App\Constants\ScheduleStatus;
use App\Constants\ScheduleType;
use App\Models\ScheduleInstance;
use App\Models\SchoolClass;
use App\Repositories\ScheduleInstanceRepository;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
            ->query(function (ScheduleInstanceRepository $instanceRepository) {
                return $instanceRepository->query()
                    ->where('class_id', $this->record->id)
                    ->with([
                        'room',
                        'teacher',
                        'attendanceSession' => function ($query) {
                            $query->withCount([
                                'attendanceRecords as present_count' => function ($q) {
                                    $q->whereIn('status', [
                                        AttendanceStatus::Present->value,
                                        AttendanceStatus::Late->value,
                                    ]);
                                }
                            ]);
                        }
                    ])
                    ->orderBy('date', 'desc');
            })
            ->recordClasses(fn(ScheduleInstance $record) => Carbon::parse($record->date)->isToday()
                ? 'bg-primary-500/10 dark:bg-primary-500/20 border-l-4 border-primary-500'
                : null
            )
            ->columns([
                TextColumn::make('date')
                    ->label('Ngày học')
                    ->date('d/m/Y')
                    ->description(fn(ScheduleInstance $record) => $record->start_time . ' - ' . $record->end_time)
                    ->sortable()
                    ->formatStateUsing(function(ScheduleInstance $record){
                        return $record->date;
                    })
                    ->color(fn(ScheduleInstance $record) => Carbon::parse($record->date)->isPast() && !Carbon::parse($record->date)->isToday() && $record->status === ScheduleStatus::Upcoming ? 'danger' : null),

                TextColumn::make('schedule_type')
                    ->label('Loại')
                    ->badge()
                    ->color(fn(ScheduleType $state) => $state->colorFilament())
                    ->formatStateUsing(fn(ScheduleType $state) => $state->label()),

                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn(ScheduleStatus $state) => $state->label())
                    ->color(fn(ScheduleStatus $state) => match ($state) {
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

                TextColumn::make('attendanceSession.present_count')
                    ->label('Số HS có mặt')
                    ->badge()
                    ->color('success')
                    ->alignCenter()
                    ->default(0),

                TextColumn::make('attendanceSession.general_note')
                    ->label('Ghi chú')
                    ->placeholder('---')
                    ->wrap(),

            ])
            ->filters(
                filters: [
                    Filter::make('filters')
                        ->columns(5)
                        ->columnSpanFull()
                        ->schema([
                            DatePicker::make('start_date')
                                ->label('Từ ngày')
                                ->format('d/m/Y'),
                            DatePicker::make('end_date')
                                ->label('Đến ngày')
                                ->format('d/m/Y'),
                        ])
                        ->query(function (Builder $query, array $data): Builder {
                            return $query
                                ->when($data['start_date'], fn($query) => $query->where('date', '>=', $data['start_date']))
                                ->when($data['end_date'], fn($query) => $query->where('date', '<=', $data['end_date']));
                        }),
                ],
                layout: FiltersLayout::AboveContent
            )
            ->recordActions([
                // 1. Xem điểm danh / Bắt đầu điểm danh
//                Action::make('view_attendance')
//                    ->label(fn (ScheduleInstance $record) => $record->attendanceSession ? 'Xem điểm danh' : 'Bắt đầu điểm danh')
//                    ->icon(Heroicon::ClipboardDocumentCheck)
//                    ->color('success')
//                    ->action(function (ScheduleInstance $record) {
//                        if ($record->attendanceSession) {
//                            return redirect("/admin/attendance-sessions/{$record->attendanceSession->id}");
//                        }
//                        return redirect("/admin/attendance-sessions/create?schedule_instance_id={$record->id}");
//                    }),

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
