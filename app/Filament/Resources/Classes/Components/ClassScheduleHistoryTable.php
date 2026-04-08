<?php

namespace App\Filament\Resources\Classes\Components;

use App\Constants\ScheduleStatus;
use App\Constants\ScheduleType;
use App\Filament\Resources\AttendanceSessions\AttendanceSessionResource;
use App\Filament\Resources\Classes\Actions\CancelScheduleInstanceAction;
use App\Filament\Resources\Classes\Actions\CreateMakeupSessionAction;
use App\Filament\Resources\Classes\Actions\EditScheduleInstanceAction;
use App\Models\ScheduleInstance;
use App\Models\SchoolClass;
use App\Repositories\ScheduleInstanceRepository;
use App\Services\AttendanceService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
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
                $query = $instanceRepository->query();
                return $instanceRepository->getListingQuery($query)
                    ->where('schedule_instances.class_id', $this->record->id)
                    ->orderBy('schedule_instances.date', 'asc');
            })
            // Color today record
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
                    ->formatStateUsing(function (ScheduleInstance $record) {
                        $date = \Illuminate\Support\Carbon::parse($record->date)->locale('vi');
                        $state = "{$date->translatedFormat('l')}, {$date->format('d/m/Y')}";
                        return $record->isOverdueWithoutAttendance()
                            ? "{$state} - (Cảnh báo: Chưa mở lớp)"
                            : $state;
                    })
                    ->color(fn(ScheduleInstance $record) => $record->isOverdueWithoutAttendance() ? 'danger' : null),

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
                    ->label('Điểm danh')
                    ->badge()
                    ->formatStateUsing(fn($state, ScheduleInstance $record) => $state . ' / ' . $record->active_students_count)
                    ->color('primary')
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
                                ->default(now())
                                ->displayFormat('d/m/Y')
                                ->format('Y-m-d'),
                            DatePicker::make('end_date')
                                ->label('Đến ngày')
                                ->displayFormat('d/m/Y')
                                ->format('Y-m-d'),
                        ])
                        ->query(function (Builder $query, array $data, ScheduleInstanceRepository $repository): Builder {
                            return $repository->setFilters($query, $data);
                        }),
                ],
                layout: FiltersLayout::AboveContent
            )
            ->recordActions([
                // badge học bù vào ngày
                Action::make('makeup_badge')
                    ->label(fn (ScheduleInstance $record) => "Bù ngày: " . \Carbon\Carbon::parse($record->makeupInstance->date)->format('d/m/Y'))
                    ->color('red')
                    ->badge()
                    ->icon(Heroicon::CalendarDays)
                    ->visible(fn (ScheduleInstance $record) => $record->makeupInstance !== null)
                    ->disabled(),

                // 1. Xem điểm danh / Bắt đầu điểm danh
                Action::make('view_attendance')
                    ->label(fn(ScheduleInstance $record) => $record->hasAttendance() ? 'Xem điểm danh' : 'Bắt đầu điểm danh')
                    ->icon(Heroicon::ClipboardDocumentCheck)
                    ->hidden(fn(ScheduleInstance $record) => $record->isDayOff())
                    ->color(fn(ScheduleInstance $record) => $record->attendanceSession ? 'info' : 'success')
                    ->action(function (ScheduleInstance $record, AttendanceService $attendanceService) {
                        $result = $attendanceService->startOrGetSession($record);
                        if ($result->isSuccess()) {
                            $data = $result->getData();
                            $this->redirect(AttendanceSessionResource::getUrl('view', ['record' => $data]));
                        } else {
                            // Bắt lỗi Validation và hiện Notification góc phải
                            Notification::make()
                                ->title('Lỗi')
                                ->body($result->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),



                // 2. Tạo buổi bù
                CreateMakeupSessionAction::make('create_makeup'),

                // 3. Hủy buổi
                CancelScheduleInstanceAction::make("cancel_instance"),

                // 4. Chỉnh sửa buổi học
                EditScheduleInstanceAction::make("edit_instance"),

            ]);
    }

    public function render(): View
    {
        return view('filament.pages.classes.class-schedule-history-table');
    }
}
