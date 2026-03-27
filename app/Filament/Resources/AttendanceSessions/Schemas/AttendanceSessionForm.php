<?php

namespace App\Filament\Resources\AttendanceSessions\Schemas;

use App\Constants\AttendanceSessionStatus;
use App\Constants\AttendanceStatus;
use App\Models\ClassEnrollment;
use App\Models\Student;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttendanceSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Thông tin buổi học')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('class_id')
                                ->relationship('class', 'name')
                                ->label('Lớp học')
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    static::loadStudentsForClass($state, $set);
                                }),

                            Select::make('teacher_id')
                                ->relationship('teacher', 'full_name')
                                ->label('Giáo viên')
                                ->required()
                                ->searchable(),

                            DatePicker::make('session_date')
                                ->label('Ngày học')
                                ->required()
                                ->default(now()),

                            Select::make('status')
                                ->label('Trạng thái')
                                ->options(AttendanceSessionStatus::options())
                                ->default(AttendanceSessionStatus::Draft)
                                ->required(),
                        ]),

                        Textarea::make('lesson_content')
                            ->label('Nội dung bài học')
                            ->placeholder('Hôm nay học gì?')
                            ->columnSpanFull(),
                    ]),

                Section::make('Điểm danh học sinh')
                    ->schema([
                        Repeater::make('attendanceRecords')
                            ->label(false)
                            ->relationship('attendanceRecords')
                            ->schema([
                                Select::make('student_id')
                                    ->label('Học sinh')
                                    ->options(Student::pluck('full_name', 'id'))
                                    ->disabled()
                                    ->dehydrated()
                                    ->required(),

                                Select::make('status')
                                    ->label('Trạng thái')
                                    ->options(AttendanceStatus::options())
                                    ->default(AttendanceStatus::Present)
                                    ->required(),

                                TimePicker::make('check_in_time')
                                    ->label('Giờ vào')
                                    ->default(now()->format('H:i')),

                                TextInput::make('teacher_comment')
                                    ->label('Nhận xét')
                                    ->placeholder('Nhận xét học viên...'),
                            ])
                            ->columns(4)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            // Đảm bảo nạp dữ liệu khi form khởi tạo (Hydrate)
                            ->afterStateHydrated(function ($component, $state, $get, $set) {
                                if (empty($state) && $classId = $get('class_id')) {
                                    static::loadStudentsForClass($classId, $set);
                                }
                            })
                            ->columnSpanFull()
                    ])
            ]);
    }

    /**
     * Nạp học sinh vào Repeater
     */
    protected static function loadStudentsForClass($classId, callable $set): void
    {
        if (!$classId) {
            $set('attendanceRecords', []);
            return;
        }

        $students = ClassEnrollment::where('class_id', $classId)
            ->whereNull('left_at')
            ->get()
            ->map(fn($enrollment) => [
                'student_id' => (string) $enrollment->student_id,
                'status' => AttendanceStatus::Present->value,
                'check_in_time' => now()->format('H:i'),
            ])
            ->toArray();

        // Ép kiểu array và gán lại toàn bộ state
        $set('attendanceRecords', $students);
    }
}
