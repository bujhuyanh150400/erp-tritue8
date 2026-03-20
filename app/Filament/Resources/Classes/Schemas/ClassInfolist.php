<?php

namespace App\Filament\Resources\Classes\Schemas;

use App\Constants\ClassStatus;
use App\Constants\GradeLevel;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClassInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Thông tin lớp học')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('code')->label('Mã lớp'),
                            TextEntry::make('name')->label('Tên lớp'),
                            TextEntry::make('status')
                                ->label('Trạng thái')
                                ->badge()
                                ->formatStateUsing(fn (ClassStatus $state): string => $state->label())
                                ->color(fn (ClassStatus $state): string => match ($state) {
                                    ClassStatus::Active => 'success',
                                    ClassStatus::Suspended => 'warning',
                                    ClassStatus::Ended => 'danger',
                                    default => 'gray',
                                }),
                        ]),
                        Grid::make(2)->schema([
                            TextEntry::make('subject.name')->label('Môn học'),
                            TextEntry::make('teacher.full_name')->label('Giáo viên'),
                            TextEntry::make('grade_level')
                                ->label('Khối')
                                ->badge()
                                ->formatStateUsing(fn (GradeLevel $state): string => $state->label()),
                            TextEntry::make('max_students')->label('Sĩ số tối đa'),
                            TextEntry::make('base_fee_per_session')->label('Học phí/buổi')->money('VND'),
                            TextEntry::make('teacher_salary_per_session')->label('Lương GV/buổi')->money('VND'),
                            TextEntry::make('start_at')->label('Ngày khai giảng')->date('d/m/Y'),
                            TextEntry::make('end_at')->label('Ngày kết thúc')->date('d/m/Y'),
                        ]),
                    ])
            ]);
    }
}
