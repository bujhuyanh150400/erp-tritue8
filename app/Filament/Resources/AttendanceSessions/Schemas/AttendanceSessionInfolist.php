<?php

namespace App\Filament\Resources\AttendanceSessions\Schemas;

use App\Constants\AttendanceSessionStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttendanceSessionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Chi tiết buổi điểm danh')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('class.name')->label('Lớp học'),
                            TextEntry::make('teacher.full_name')->label('Giáo viên'),
                            TextEntry::make('session_date')->label('Ngày học')->date('d/m/Y'),
                            TextEntry::make('status')
                                ->label('Trạng thái')
                                ->badge()
                                ->formatStateUsing(fn (AttendanceSessionStatus $state): string => $state->label())
                                ->color(fn (AttendanceSessionStatus $state): string => match ($state) {
                                    AttendanceSessionStatus::Draft => 'gray',
                                    AttendanceSessionStatus::Completed => 'success',
                                    AttendanceSessionStatus::Locked => 'danger',
                                    default => 'gray',
                                }),
                        ]),

                        TextEntry::make('lesson_content')->label('Nội dung bài học')->columnSpanFull(),
                        TextEntry::make('homework')->label('Bài tập về nhà')->columnSpanFull(),
                        TextEntry::make('next_session_note')->label('Dặn dò buổi sau')->columnSpanFull(),
                        TextEntry::make('general_note')->label('Ghi chú chung')->columnSpanFull(),
                    ])
            ]);
    }
}
