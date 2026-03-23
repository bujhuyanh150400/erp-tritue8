<?php

namespace App\Filament\Resources\Classes\Tables;

use App\Constants\ClassStatus;
use App\Constants\GradeLevel;
use App\Filament\Components\CommonAction;
use App\Filament\Components\CustomSelect;
use App\Filament\Resources\Classes\Components\AddStudentToClassAction;
use App\Filament\Resources\Classes\Components\ChangeClassStatusAction;
use App\Filament\Resources\Classes\Components\ChangeTeacherAction;
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

                TextColumn::make('base_fee_per_session')
                    ->label('Học phí')
                    ->money('VND')
                    ->sortable(),

                TextColumn::make('teacher_salary_per_session')
                    ->label('Lương GV')
                    ->money('VND')
                    ->sortable(),

                TextColumn::make('schedule')
                    ->label('Lịch học')
                    ->getStateUsing(function (SchoolClass $record) {
                        $templates = $record->scheduleTemplates;
                        if ($templates->isEmpty()) return 'Chưa có lịch';

                        // Gom nhóm theo Giờ học (ví dụ: '17:00-19:00' => ['T2', 'T4'])
                        $grouped = [];
                        foreach ($templates as $t) {
                            $timeKey = \Carbon\Carbon::parse($t->start_time)->format('H:i') . ' - ' . \Carbon\Carbon::parse($t->end_time)->format('H:i');
                            $grouped[$timeKey][] = $t->day_of_week->label();
                        }

                        // Format thành chuỗi "T2, T4 | 17:00 - 19:00"
                        $output = [];
                        foreach ($grouped as $time => $days) {
                            $output[] = implode(', ', $days) . ' | ' . $time;
                        }
                        return implode('<br>', $output);
                    })
                    ->html()
                    ->wrap(),

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
                    // 1. Xem chi tiết & Chỉnh sửa
                    CommonAction::viewAction(),
                    CommonAction::editAction(),
                    AddStudentToClassAction::make(),


                    // 2. Điểm danh & DS Học sinh (Chuyển hướng trang)
//                    Action::make('attendance')->label('Điểm danh')->icon('heroicon-m-clipboard-document-check')->color('success')->url(fn ($record) => "#"), // Thay route thực tế

//                    // 3. Nghiệp vụ trạng thái (Tạm ngưng / Kết thúc)
//                    Action::make('pause')
//                        ->label('Tạm ngưng')
//                        ->icon('heroicon-m-pause-circle')
//                        ->color('warning')
//                        ->visible(fn ($record) => $record->status === ClassStatus::Active)
//                        ->requiresConfirmation()
//                        ->action(fn ($record, SchoolClassService $service) => $service->changeStatus($record, ClassStatus::Paused)),
//
//                    Action::make('finish')
//                        ->label('Kết thúc lớp')
//                        ->icon('heroicon-m-stop-circle')
//                        ->color('danger')
//                        ->visible(fn ($record) => in_array($record->status, [ClassStatus::Active, ClassStatus::Paused]))
//                        ->requiresConfirmation()
//                        ->action(fn ($record, SchoolClassService $service) => $service->changeStatus($record, ClassStatus::Finished)),
//
//                    // 4. Nhân bản (Clone)
//                    Action::make('clone')
//                        ->label('Nhân bản lớp học')
//                        ->icon('heroicon-m-document-duplicate')
//                        ->color('gray')
//                        ->form([
//                            TextInput::make('new_code')->label('Mã lớp mới')->required()->unique('classes', 'code'),
//                            TextInput::make('new_name')->label('Tên lớp mới')->required(),
//                        ])
//                        ->action(function (SchoolClass $record, array $data, SchoolClassService $service) {
//                            $service->cloneClass($record, $data['new_code'], $data['new_name']);
//                            \Filament\Notifications\Notification::make()->success()->title('Nhân bản thành công')->send();
//                        }),
                    ChangeTeacherAction::make(),
                    ChangeClassStatusAction::make(),
                ])
            ]);
    }
}
