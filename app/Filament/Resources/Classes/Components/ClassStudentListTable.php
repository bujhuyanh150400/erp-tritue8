<?php

namespace App\Filament\Resources\Classes\Components;

use App\Filament\Resources\Classes\Actions\AddStudentToClassAction;
use App\Filament\Resources\Classes\Actions\EditStudentFeeAction;
use App\Filament\Resources\Classes\Actions\LeaveClassAction;
use App\Filament\Resources\Classes\Actions\TransferStudentClassAction;
use App\Models\SchoolClass;
use App\Repositories\ClassEnrollmentRepository;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ClassStudentListTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public SchoolClass $record;

    public function table(Table $table): Table
    {
        return $table
            ->query(function (ClassEnrollmentRepository $repository) {
                return $repository->getEnrollmentsWithAttendanceStatsQuery($this->record->id);
            })
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('Học sinh')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('enrolled_at')
                    ->label('Ngày vào lớp')
                    ->date('d/m/Y'),

                TextColumn::make('fee_per_session')
                    ->label('Học phí/buổi')
                    ->money('VND')
                    ->default($this->record->base_fee_per_session), // Fallback về học phí gốc của lớp

                // CỘT THỐNG KÊ ĐIỂM DANH
                TextColumn::make('total_present')
                    ->label('Tổng buổi có mặt')
                    ->formatStateUsing(fn ($state) => "{$state} buổi")
                    ->badge()
                    ->color('success'),

                TextColumn::make('total_absent')
                    ->label('Tổng buổi nghỉ')
                    ->formatStateUsing(fn ($state) => "{$state} buổi")
                    ->badge()
                    ->color(fn ($state) => $state > 2 ? 'danger' : 'warning')
                    ->description(fn ($state) => $state > 2 ? 'Cần chú ý' : null),
            ])
            ->headerActions([
                // Thêm học sinh vào lớp
                AddStudentToClassAction::make()
                    ->record($this->record),
            ])
            ->recordActions([
                // Sửa học phí
                EditStudentFeeAction::make(),
                // Chuyển lớp
                TransferStudentClassAction::make(),
                // Cho nghỉ
                LeaveClassAction::make(),
            ]);
    }

    public function render(): View
    {
        return view('filament.common.view-table');
    }
}
