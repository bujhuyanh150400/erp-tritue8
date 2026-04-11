<?php

namespace App\Filament\Resources\AttendanceSessions\Tables;

use App\Constants\AttendanceSessionStatus;
use App\Filament\Components\CommonAction;
use App\Repositories\AttendanceSessionRepository;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceSessionTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query, AttendanceSessionRepository $attendanceRepo) {
                return $attendanceRepo->getListingQuery($query);
            })
            ->columns([
                TextColumn::make('class.name')
                    ->label('Lớp học'),

                TextColumn::make('teacher.full_name')
                    ->label('Giáo viên'),

                TextColumn::make('session_date')
                    ->label('Ngày học')
                    ->date('d/m/Y'),

                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (AttendanceSessionStatus $state): string => $state->label())
                    ->color(fn (AttendanceSessionStatus $state): string => $state->colorFilament()),
                TextColumn::make('attendance_records_count')
                    ->label('Điểm danh')
                    ->counts('attendanceRecords'),
            ])
            ->recordActions([
                CommonAction::viewAction()
            ]);
    }
}
