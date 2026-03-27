<?php

namespace App\Filament\Resources\AttendanceSessions\Tables;

use App\Constants\AttendanceSessionStatus;
use App\Models\AttendanceSession;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendanceSessionTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('class.name')
                    ->label('Lớp học')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('teacher.full_name')
                    ->label('Giáo viên')
                    ->searchable(),

                TextColumn::make('session_date')
                    ->label('Ngày học')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (AttendanceSessionStatus $state): string => $state->label())
                    ->color(fn (AttendanceSessionStatus $state): string => match ($state) {
                        AttendanceSessionStatus::Draft => 'gray',
                        AttendanceSessionStatus::Completed => 'success',
                        AttendanceSessionStatus::Locked => 'danger',
                    })
                    ->sortable(),

                TextColumn::make('attendance_records_count')
                    ->label('Sĩ số')
                    ->counts('attendanceRecords'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
