<?php

namespace App\Filament\Pages\ScheduleCalendar\Components;

use App\Constants\FeeType;
use App\Constants\GradeLevel;
use App\Filament\Components\CommonNotification;
use App\Filament\Components\CustomSelect;
use App\Models\Student;
use App\Repositories\StudentRepository;
use App\Services\ClassScheduleService;
use App\Services\ClassService;
use App\Services\RoomService;
use App\Services\TeacherService;
use Filament\Actions\BulkAction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

class CreateExtraScheduleTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;


    public function table(Table $table): Table
    {
        return $table
            ->query(function (StudentRepository $studentRepo) {
                $query = $studentRepo->query();
                return $studentRepo->getListingQuery($query)
                    ->whereHas("user", function (Builder $query) {
                        $query->where("is_active", true);
                    });
            })
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([10, 20, 50, 100])
            ->columns([
                // Các cột hiển thị tên học sinh, lớp học của bạn...
                TextColumn::make('parent_info')
                    ->label('Học sinh')
                    ->state(fn(Student $record) => $record->parent_name)
                    ->icon(Heroicon::User)
                    ->iconColor('gray')
                    ->description(fn(Student $record) => "SĐT: {$record->parent_phone}"),
                TextColumn::make('grade_level')
                    ->label('Khối')
                    ->formatStateUsing(fn(GradeLevel $state) => $state->label())
                    ->badge(),
                TextColumn::make('activeClassEnrollments.class.name')
                    ->label('Lớp đang học')
                    ->badge()
                    ->separator(','),
            ])
            ->filters([
                Filter::make('filters')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('keyword')
                            ->placeholder('Tìm kiếm theo tên, ID, SĐT Phụ huynh,...')
                            ->label('Tìm kiếm'),
                        Select::make('grade_level')
                            ->label('Khối')
                            ->searchable()
                            ->options(GradeLevel::options()),
                        CustomSelect::make('class_id')
                            ->label('Lớp học')
                            ->getOptionSelectService(ClassService::class),
                    ])
                    ->query(function (Builder $query, array $data, StudentRepository $studentRepo): Builder {
                        return $studentRepo->setFilters($query, $data);
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('create_extra_schedule')
                    ->label('Tạo lịch')
                    ->icon('heroicon-m-plus-circle')
                    ->color('success')
                    ->modalHeading('Thiết lập thông tin lịch học tăng cường')
                    ->modalSubmitActionLabel('Lưu lịch & Thêm học sinh')
                    ->modalWidth(Width::FiveExtraLarge)
                    ->schema([
                        Grid::make(3)->schema([
                            DatePicker::make('date')
                                ->label('Ngày học')
                                ->rules(['required'])
                                ->markAsRequired()
                                ->validationMessages([
                                    'required' => 'Vui lòng chọn ngày học.',
                                ])
                                ->native(false),
                            TimePicker::make('start_time')
                                ->label('Bắt đầu')
                                ->rules(['required'])
                                ->markAsRequired()
                                ->validationMessages([
                                    'required' => 'Vui lòng chọn giờ bắt đầu.',
                                ])
                                ->seconds(false),
                            TimePicker::make('end_time')
                                ->label('Kết thúc')
                                ->rules(['required'])
                                ->markAsRequired()
                                ->validationMessages([
                                    'required' => 'Vui lòng chọn giờ kết thúc.',
                                ])
                                ->seconds(false),
                        ]),
                        Grid::make(2)->schema([
                            CustomSelect::make('teacher_id')
                                ->label('Giáo viên')
                                ->rules(['required'])
                                ->markAsRequired()
                                ->validationMessages([
                                    'required' => 'Vui lòng chọn giáo viên.',
                                ])
                                ->getOptionSelectService(TeacherService::class),
                            CustomSelect::make('room_id')
                                ->label('Phòng học')
                                ->rules(['required'])
                                ->markAsRequired()
                                ->getOptionSelectService(RoomService::class)
                                ->validationMessages([
                                    'required' => 'Vui lòng chọn phòng học.',
                                ]),
                        ]),
                        Radio::make('fee_type')
                            ->label('Học phí học sinh')
                            ->options([
                                FeeType::Free->value => FeeType::Free->label(),
                                FeeType::Custom->value => FeeType::Custom->label(),
                            ])
                            ->default(FeeType::Free->value)
                            ->inline()
                            ->markAsRequired()
                            ->rules(['required'])
                            ->validationMessages([
                                'required' => 'Vui lòng chọn loại học phí.',
                            ])
                            ->live(),
                        Grid::make(2)->schema([
                            TextInput::make('custom_fee_per_session')
                                ->label('Học phí tùy chỉnh (VNĐ)')
                                ->numeric()
                                ->rules(['required'])
                                ->markAsRequired()
                                ->validationMessages([
                                    'required' => 'Vui lòng nhập học phí tùy chỉnh.',
                                ])
                                ->visible(fn(Get $get) => $get('fee_type') == FeeType::Custom->value),

                            TextInput::make('salary')
                                ->label('Lương GV ca này (VNĐ)')
                                ->helperText('Để trống sẽ tính theo lương chuẩn của giáo viên đã chọn.')
                                ->numeric(),
                        ]),
                    ])

                    // 3. XỬ LÝ LƯU DB KHI ẤN SUBMIT
                    ->action(function (array $data, Collection $records, ClassScheduleService $service) {
                        $result = $service->createExtraSchedule($data, $records);
                        if ($result->isError()) {
                            CommonNotification::error()
                                ->body($result->getMessage())
                                ->send();
                            throw new Halt();
                        }
                        CommonNotification::success()
                            ->body("Tạo lịch học thành công.")
                            ->send();
                        $this->redirect(request()->header('Referer'));
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public function render()
    {
        return view('filament.common.view-table');
    }
}
