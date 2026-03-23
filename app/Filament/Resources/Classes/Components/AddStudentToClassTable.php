<?php

namespace App\Filament\Resources\Classes\Components;

use App\Constants\Gender;
use App\Constants\GradeLevel;
use App\Models\Student;
use App\Repositories\StudentRepository;
use App\Services\ClassService;
use Filament\Actions\BulkAction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;


/**
 * Component để thêm học sinh vào lớp
 */
class AddStudentToClassTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public int $classId;

    public function mount(int $classId): void
    {
        $this->classId = $classId;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (StudentRepository $studentRepository) {
                return $studentRepository->getAvailableStudentsForClassQuery($this->classId);
            })
            ->columns([
                TextColumn::make('id_display')
                    ->label('Học sinh')
                    ->description(fn(Student $record) => "ID: {$record->user_id}")
                    ->state(fn(Student $record) => $record->full_name),

                TextColumn::make('dob')
                    ->label('Ngày sinh')
                    ->date('d/m/Y'),

                TextColumn::make('gender')
                    ->label('Giới tính')
                    ->formatStateUsing(fn(Gender $state) => $state->label()),

                TextColumn::make('grade_level')
                    ->label('Khối')
                    ->formatStateUsing(fn(GradeLevel $state) => $state->label())
                    ->badge(),

                TextColumn::make('parent_info')
                    ->label('Phụ huynh')
                    ->state(fn(Student $record) => $record->parent_name)
                    ->icon(Heroicon::User)
                    ->iconColor('gray')
                    ->description(fn(Student $record) => "SĐT: {$record->parent_phone}"),
            ])
            ->filters(
                filters: [
                    Filter::make('filters')
                        ->columns(5)
                        ->columnSpanFull()
                        ->schema([
                            TextInput::make('keyword')
                                ->placeholder('Tìm kiếm theo tên, ID, SĐT Phụ huynh,...')
                                ->label('Tìm kiếm'),
                            Select::make('grade_level')
                                ->label('Khối')
                                ->searchable()
                                ->options(GradeLevel::options()),
                        ])
                        ->query(function (Builder $query, array $data, StudentRepository $studentRepo): Builder {
                            return $studentRepo->setFilters($query, $data);
                        }),
                ],
                layout: FiltersLayout::AboveContent
            )
            ->toolbarActions([
                // NÚT CHỨC NĂNG THÊM NHIỀU HỌC SINH VÀO LỚP
                BulkAction::make('enroll_selected')
                    ->label('Thêm các em đã chọn vào lớp')
                    ->icon(Heroicon::UserPlus)
                    ->color('success')
                    ->requiresConfirmation()
                    ->schema([
                        DatePicker::make('enrolled_at')
                            ->label('Ngày bắt đầu học')
                            ->required()
                            ->default(now())
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        TextInput::make('fee_per_session')
                            ->label('Học phí tùy chỉnh / buổi')
                            ->numeric()
                            ->helperText('Bỏ trống để dùng học phí gốc của lớp')
                            ->suffix('VNĐ'),

                        Textarea::make('note')
                            ->label('Ghi chú chung')
                            ->placeholder('Bỏ trống nếu không có ghi chú')
                            ->rows(2),
                    ])
                    ->action(function (Collection $records, array $data, ClassService $service) {
                        // Lấy lớp học theo ID
                        $classResult = $service->findClassById($this->classId);
                        if ($classResult->isError()) {
                            Notification::make()
                                ->danger()
                                ->title('Lỗi')
                                ->body($classResult->getMessage())
                                ->send();
                            return;
                        }

                        $result = $service->addStudentsToClassroom(
                            schoolClass: $classResult->getData(),
                            students: $records,
                            data: $data
                        );
                        if ($result->isSuccess()) {
                            Notification::make()
                                ->success()
                                ->title('Hoàn tất')
                                ->body("Đã thêm thành công tất cả các học sinh vào lớp.")
                                ->send();
                            // Refresh lại toàn bộ trang để cập nhật danh sách ở bảng cha
                            $this->redirect(request()->header('Referer'));
                        } else {
                            Notification::make()
                                ->danger()
                                ->title('Lỗi')
                                ->body($result->getMessage())
                                ->send();
                        }
                    })
            ]);
    }

    public function render(): View
    {
        return view('filament.pages.classes.add-student-to-class');
    }
}
