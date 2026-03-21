<?php

namespace App\Filament\Resources\Classes\Schemas;

use App\Constants\ClassStatus;
use App\Constants\EmployeeStatus;
use App\Constants\GradeLevel;
use App\Constants\RoomStatus;
use App\Models\Subject;
use App\Models\Teacher;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClassForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Thông tin lớp học')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('code')
                                ->label('Mã lớp')
                                ->required()
                                ->unique(table: 'classes', column: 'code', ignoreRecord: true)
                                ->disabled(fn ($record) => $record !== null),

                            TextInput::make('name')
                                ->label('Tên lớp')
                                ->required(),

                            Select::make('grade_level')
                                ->label('Khối')
                                ->options(GradeLevel::options())
                                ->required(),

                            Select::make('subject_id')
                                ->label('Môn học')
                                ->options(Subject::where('is_active', true)->pluck('name', 'id'))
                                ->required()
                                ->searchable()
                                ->disabled(fn ($record) => $record && $record->scheduleInstances()->exists()),

                            Select::make('teacher_id')
                                ->label('Giáo viên')
                                ->options(Teacher::where('status', EmployeeStatus::Active)->pluck('full_name', 'id'))
                                ->required()
                                ->searchable(),

                            Select::make('status')
                                ->label('Trạng thái')
                                ->options(ClassStatus::options())
                                ->default(ClassStatus::Active)
                                ->required(),
                        ]),

                        Grid::make(3)->schema([
                            TextInput::make('base_fee_per_session')
                                ->label('Học phí/buổi')
                                ->numeric()
                                ->required()
                                ->suffix('VND'),

                            TextInput::make('teacher_salary_per_session')
                                ->label('Lương GV/buổi')
                                ->numeric()
                                ->required()
                                ->suffix('VND'),

                            TextInput::make('max_students')
                                ->label('Sĩ số tối đa')
                                ->numeric()
                                ->required()
                                ->minValue(1),
                        ]),

                        Grid::make(2)->schema([
                            DatePicker::make('start_at')
                                ->label('Ngày khai giảng')
                                ->required(),

                            DatePicker::make('end_at')
                                ->label('Ngày kết thúc'),
                        ]),
                    ])
            ]);
    }
}
