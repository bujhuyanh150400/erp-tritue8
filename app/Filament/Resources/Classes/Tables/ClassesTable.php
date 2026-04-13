<?php

namespace App\Filament\Resources\Classes\Tables;

use App\Constants\ClassStatus;
use App\Constants\GradeLevel;
use App\Filament\Components\CommonAction;
use App\Filament\Components\CustomSelect;
use App\Filament\Resources\Classes\Actions\AddStudentToClassAction;
use App\Filament\Resources\Classes\Actions\ChangeClassStatusAction;
use App\Filament\Resources\Classes\Actions\CreateScheduleTemplateAction;
use App\Models\SchoolClass;
use App\Repositories\ClassRepository;
use App\Services\SubjectService;
use App\Services\TeacherService;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ClassesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query, ClassRepository $classRepository) {
                $classRepository->getListingQuery($query);
            })
            ->columns([
                TextColumn::make('code')
                    ->label('Mã lớp')
                    ->description(fn(SchoolClass $record) => "Tên lớp: {$record->name}")
                    ->weight('bold'),

                TextColumn::make('subject.name')
                    ->description(fn(SchoolClass $record) => "Khối: {$record->grade_level->label()}")
                    ->label('Môn học'),

                TextColumn::make('teacher.full_name')
                    ->description(fn(SchoolClass $record) => "Sĩ số: {$record->active_students_count}")
                    ->label('Giáo viên'),

                TextColumn::make('start_at')
                    ->label('Thời gian')
                    ->formatStateUsing(function (SchoolClass $record) {
                        $startDate = $record->start_at ? Carbon::parse($record->start_at)->format('d/m/Y') : 'Chưa bắt đầu';
                        $endDate = $record->end_at ? Carbon::parse($record->end_at)->format('d/m/Y') : 'Chưa kết thúc';
                        return "{$startDate} - {$endDate}";
                    }),

                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn(ClassStatus $state): string => $state->label())
                    ->color(fn(ClassStatus $state): string => match ($state) {
                        ClassStatus::Active => 'success',
                        ClassStatus::Suspended => 'warning',
                        ClassStatus::Ended => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Filter::make('custom_filters')
                    ->schema([
                        TextInput::make('keyword')
                            ->label('Tìm kiếm (Tên, Mã, GV)')
                            ->placeholder('Nhập từ khóa...'),

                        Select::make('status')
                            ->label('Trạng thái')
                            ->searchable()
                            ->options(ClassStatus::class),

                        CustomSelect::make('subject_id')
                            ->label('Môn học')
                            ->getOptionSelectService(SubjectService::class),

                        CustomSelect::make('teacher_id')
                            ->label('Giáo viên')
                            ->getOptionSelectService(TeacherService::class),

                        Select::make('grade_level')
                            ->label('Khối')
                            ->searchable()
                            ->options(GradeLevel::class),
                    ])
                    ->query(function (Builder $query, array $data, ClassRepository $repo): Builder {
                        return $repo->setFilters($query, $data);
                    })
                    ->columnSpanFull()
                    ->columns(5),
            ])
            ->recordActions([
                ActionGroup::make([
                    // Xem chi tiết
                    CommonAction::viewAction(),
                    // Edit action
                    CommonAction::editAction(),
                    // Tạo lịch học cố định
                    CreateScheduleTemplateAction::make('create_schedule_template'),
                    // Thêm học sinh
                    AddStudentToClassAction::make(),
                    // Thay đổi trạng thái
                    ChangeClassStatusAction::make(),
                ])
                    ->color("gray")
                    ->label("Thao tác")
                    ->button()
            ]);
    }
}
