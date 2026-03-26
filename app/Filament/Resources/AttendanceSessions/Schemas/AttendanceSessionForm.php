<?php

namespace App\Filament\Resources\AttendanceSessions\Schemas;

use App\Constants\AttendanceSessionStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttendanceSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Thông tin buổi điểm danh')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('class_id')
                                ->relationship('class', 'name')
                                ->label('Lớp học')
                                ->required()
                                ->searchable(),

                            Select::make('teacher_id')
                                ->relationship('teacher', 'full_name')
                                ->label('Giáo viên')
                                ->required()
                                ->searchable(),

                            DatePicker::make('session_date')
                                ->label('Ngày học')
                                ->required(),

                            Select::make('status')
                                ->label('Trạng thái')
                                ->options(AttendanceSessionStatus::options())
                                ->default(AttendanceSessionStatus::Draft)
                                ->required(),
                        ]),

                        Textarea::make('lesson_content')
                            ->label('Nội dung bài học')
                            ->columnSpanFull(),

                        Textarea::make('homework')
                            ->label('Bài tập về nhà')
                            ->columnSpanFull(),

                        Textarea::make('next_session_note')
                            ->label('Dặn dò buổi sau')
                            ->columnSpanFull(),

                        Textarea::make('general_note')
                            ->label('Ghi chú chung')
                            ->columnSpanFull(),
                    ])
            ]);
    }
}
