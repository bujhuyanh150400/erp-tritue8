<?php

namespace App\Filament\Resources\Classes\Components;

use App\Constants\DayOfWeek;
use App\Filament\Resources\Classes\Actions\CreateScheduleTemplateAction;
use App\Filament\Resources\Classes\Actions\EditScheduleTemplateAction;
use App\Models\ClassScheduleTemplate;
use App\Models\SchoolClass;
use App\Repositories\ClassScheduleTemplateRepository;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;

class ClassTemplateScheduleTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public SchoolClass $record;

    public function render(): View
    {
        return view('filament.pages.classes.class-template-schedule-table');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (ClassScheduleTemplateRepository $repository) {
                $query = $repository->query();
                return $repository->getListingQuery($query)
                    ->where('class_id', $this->record->id);
            })
            ->emptyStateHeading("Chưa có lịch học cố định nào")
            ->headerActions([
                CreateScheduleTemplateAction::make('create_schedule_template')
                    ->record($this->record),
            ])
            ->columns([
                // 2. Cột Thứ trong tuần
                TextColumn::make('day_of_week')
                    ->label('Thứ')
                    ->badge()
                    ->description(function (ClassScheduleTemplate $record) {
                        $start = Carbon::parse($record->start_time)->format('H:i');
                        $end = Carbon::parse($record->end_time)->format('H:i');
                        return "Khung giờ: {$start} - {$end}";
                    })
                    ->formatStateUsing(fn(DayOfWeek $state): string => $state->label()),

                // 4. Cột Giáo viên
                TextColumn::make('teacher.full_name')
                    ->label('Giáo viên')
                    ->icon('heroicon-m-academic-cap'),

                // 5. Cột Phòng học
                TextColumn::make('room.name')
                    ->label('Phòng')
                    ->badge()
                    ->color('gray'),

                // 6. Cột Thời hạn áp dụng (Start Date & End Date)
                TextColumn::make('validity_period')
                    ->label('Thời hạn áp dụng')
                    ->getStateUsing(function ($record) {
                        $start = Carbon::parse($record->start_date)->format('d/m/Y');
                        $end = $record->end_date ? Carbon::parse($record->end_date)->format('d/m/Y') : 'Vô thời hạn';
                        return "Từ {$start} đến {$end}";
                    })
                    ->description(function ($record) {
                        // Thêm dòng mô tả nhỏ: Hiện chữ "Đã hết hạn" màu đỏ nếu end_date < today
                        if ($record->end_date && Carbon::parse($record->end_date)->isPast()) {
                            return 'Đã hết hạn';
                        }
                        return 'Đang hoạt động';
                    })
                    // Tô màu đỏ nếu hết hạn
                    ->color(fn($record) => $record->end_date && Carbon::parse($record->end_date)->isPast() ? 'danger' : 'success'),

                TextColumn::make('schedule_instances_count')
                    ->label('Số buổi đã sinh')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn($state) => $state ? "{$state} buổi" : 'Chưa có'),

                TextColumn::make('schedule_instances_max_date')
                    ->label('Lịch sinh đến ngày')
                    ->color('success')
                    ->default('Chưa sinh lịch')
                    ->formatStateUsing(function ($record) {
                        if (!$record->schedule_instances_max_date) {
                            return 'Chưa sinh lịch';
                        }
                        return Carbon::parse($record->schedule_instances_max_date)->format('d/m/Y');
                    })
                    ->description(function ($record) {
                        // Dòng chữ nhỏ bên dưới: Hiển thị thời gian Tool chạy để sinh ra lịch này
                        if (!$record->schedule_instances_max_created_at) {
                            return 'Chưa từng chạy tool';
                        }
                        $time = Carbon::parse($record->schedule_instances_max_created_at)->format('H:i d/m/Y');
                        return "Tạo lúc: {$time}";
                    }),
            ])
            ->recordActions([
                EditScheduleTemplateAction::make('edit_schedule_template'),
            ]);
       }
}
