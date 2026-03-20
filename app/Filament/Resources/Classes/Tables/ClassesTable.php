<?php

namespace App\Filament\Resources\Classes\Tables;

use App\Constants\ClassStatus;
use App\Constants\GradeLevel;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClassesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['activeTemplate', 'subject', 'teacher']))
            ->columns([
                TextColumn::make('code')
                    ->label('Mã lớp')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Tên lớp')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subject.name')
                    ->label('Môn học')
                    ->sortable(),

                TextColumn::make('grade_level')
                    ->label('Khối')
                    ->badge()
                    ->formatStateUsing(fn (GradeLevel $state): string => $state->label())
                    ->sortable(),

                TextColumn::make('teacher.full_name')
                    ->label('Giáo viên')
                    ->searchable(),

                TextColumn::make('active_enrollments_count')
                    ->label('Học sinh')
                    ->counts('activeEnrollments')
                    ->sortable(),

                TextColumn::make('base_fee_per_session')
                    ->label('Học phí')
                    ->money('VND')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('teacher_salary_per_session')
                    ->label('Lương GV')
                    ->money('VND')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('schedule_summary')
                    ->label('Lịch học')
                    ->html()
                    ->state(function (SchoolClass $record) {
                        $schedules = $record->activeTemplate;
                        if ($schedules->isEmpty()) {
                            return 'Chưa có lịch';
                        }

                        return $schedules->map(function($template) {
                             $day = $template->day_of_week->label();
                             // Format time: remove seconds if possible, assuming time format H:i:s
                             $start = substr($template->start_time, 0, 5);
                             $end = substr($template->end_time, 0, 5);
                             return "{$day} | {$start}-{$end}";
                        })->join('<br>');
                    }),

                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (ClassStatus $state): string => $state->label())
                    ->color(fn (ClassStatus $state): string => match ($state) {
                        ClassStatus::Active => 'success',
                        ClassStatus::Suspended => 'warning',
                        ClassStatus::Ended => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(ClassStatus::options()),

                SelectFilter::make('subject_id')
                    ->label('Môn học')
                    ->options(Subject::pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('grade_level')
                    ->label('Khối')
                    ->options(GradeLevel::options()),

                SelectFilter::make('teacher_id')
                    ->label('Giáo viên')
                    ->options(Teacher::pluck('full_name', 'id'))
                    ->searchable(),
            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
