<?php

namespace App\Filament\Resources\Classes\Tables;

use App\Constants\ClassStatus;
use App\Constants\GradeLevel;
use App\Filament\Components\CommonAction;
use App\Filament\Components\CustomSelect;
use App\Filament\Resources\Classes\Actions\AddStudentToClassAction;
use App\Filament\Resources\Classes\Actions\ChangeClassStatusAction;
use App\Filament\Resources\Classes\Actions\ChangeTeacherAction;
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
                    ->label('Môn học'),

                TextColumn::make('grade_level')
                    ->label('Khối')
                    ->badge()
                    ->formatStateUsing(fn(GradeLevel $state): string => $state->label()),

                TextColumn::make('teacher.full_name')
                    ->label('Giáo viên'),

                TextColumn::make('active_students_count')
                    ->label('Sĩ số')
                    ->formatStateUsing(fn(int $state): string => "{$state} học sinh")
                    ->badge()
                    ->color('info'),

                TextColumn::make('start_at')
                    ->label('Thời gian')
                    ->alignCenter()
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
            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                ActionGroup::make([
                    // Xem chi tiết
                    CommonAction::viewAction(),
                    // Edit action
                    CommonAction::editAction(),
                    // Tạo lịch học cố định
                    CreateScheduleTemplateAction::make(),
                    // Thêm học sinh
                    AddStudentToClassAction::make(),
                    // Thay đổi GV
                    ChangeTeacherAction::make(),
                    // Thay đổi trạng thái
                    ChangeClassStatusAction::make(),

                ])
            ]);
    }
}
