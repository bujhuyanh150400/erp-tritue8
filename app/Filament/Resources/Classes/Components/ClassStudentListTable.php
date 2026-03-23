<?php

namespace App\Filament\Resources\Classes\Components;

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
                // Tích hợp lại Nút Thêm HS hàng loạt đã làm ở bài trước
                AddStudentToClassAction::make()
            ])
            ->recordActions([
//                // ACTION 1: THIẾT LẬP HỌC PHÍ RIÊNG
//                Tables\Actions\EditAction::make('edit_fee')
//                    ->label('Sửa học phí')
//                    ->icon('heroicon-m-currency-dollar')
//                    ->color('warning')
//                    ->form([
//                        Tables\Forms\Components\TextInput::make('fee_per_session')
//                            ->label('Học phí mới / buổi')
//                            ->numeric()
//                            ->required(),
//                    ]),
//
//                // ACTION 2: CHUYỂN LỚP (Gợi ý sườn logic)
//                Tables\Actions\Action::make('transfer_class')
//                    ->label('Chuyển lớp')
//                    ->icon('heroicon-m-arrows-right-left')
//                    ->color('info')
//                    ->form([
//                        // Tương lai bạn sẽ thêm Select Class mới ở đây
//                        Tables\Forms\Components\Placeholder::make('info')
//                            ->content('Tính năng đang được phát triển...'),
//                    ]),
//
//                // ACTION 3: CHO NGHỈ HỌC (Soft Leave)
//                Tables\Actions\Action::make('leave_class')
//                    ->label('Cho nghỉ')
//                    ->icon('heroicon-m-arrow-right-on-rectangle')
//                    ->color('danger')
//                    ->requiresConfirmation()
//                    ->modalHeading('Chốt sổ thôi học')
//                    ->modalDescription('Học sinh sẽ rời lớp nhưng dữ liệu điểm danh cũ vẫn được giữ nguyên.')
//                    ->form([
//                        Tables\Forms\Components\DatePicker::make('left_at')
//                            ->label('Ngày chính thức nghỉ')
//                            ->default(now())
//                            ->required(),
//                        Tables\Forms\Components\Textarea::make('note')
//                            ->label('Lý do nghỉ')
//                            ->required(),
//                    ])
//                    ->action(function (ClassEnrollment $record, array $data) {
//                        $record->update([
//                            'left_at' => $data['left_at'],
//                            'note' => ltrim($record->note . "\n[Nghỉ học]: " . $data['note'])
//                        ]);
//
//                        \Filament\Notifications\Notification::make()
//                            ->success()
//                            ->title('Đã chốt sổ thôi học!')
//                            ->send();
//                    }),
            ]);
    }

    public function render(): View
    {
        return view('filament.pages.classes.class-student-list-table');
    }
}
