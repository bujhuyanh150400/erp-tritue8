<?php

namespace App\Filament\Resources\Classes\Schemas;

use App\Constants\GradeLevel;
use App\Filament\Components\CustomSelect;
use App\Models\SchoolClass;
use App\Services\SubjectService;
use App\Services\TeacherService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ClassForm
{
    public static function configure(Schema $schema): Schema
    {
        // Tự động sinh mã lớp khi nhập tên lớp và khối
        $generateCode = function (Set $set, Get $get) {
            $name = $get('name');
            $grade = $get('grade_level');
            if ($name && $grade) {
                // Slugify tên lớp, viết hoa, thêm khối. Ví dụ: TOAN-A1-10
                $code = Str::upper(Str::slug($name)) . '-' . $grade;
                $set('code', $code);
            }
        };

        return $schema
            ->schema([
                Section::make('Thông tin cơ bản')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Tên lớp')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true) // Chỉ tự động sinh mã khi nhấp chuột ra ngoài ô nhập liệu
                                ->afterStateUpdated($generateCode),

                            Select::make('grade_level')
                                ->label('Khối')
                                ->searchable()
                                ->required()
                                ->options(GradeLevel::options())
                                ->live()
                                ->afterStateUpdated($generateCode),

                            TextInput::make('code')
                                ->label('Mã lớp')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->disabled(fn($livewire) => $livewire instanceof EditRecord)
                                ->helperText(fn($livewire) => $livewire instanceof EditRecord ?
                                    'Bạn không thể chỉnh sửa mã lớp khi đang chỉnh sửa.'
                                    : 'Mã tự sinh theo Tên và Khối. Bạn có thể chỉnh sửa nếu cần.'),

                            CustomSelect::make('subject_id')
                                ->label('Môn học')
                                ->required()
                                ->getOptionSelectService(SubjectService::class)
                                ->disabled(function (?SchoolClass $record, $livewire) {
                                    // Nếu là edit và lớp đã có buổi học, thì không cho đổi môn
                                    if ($livewire instanceof EditRecord && $record) {
                                        return $record->scheduleInstances()->exists();
                                    }
                                    return false;
                                })
                                ->helperText(fn (Select $component) => $component->isDisabled()
                                    ?  'Không thể đổi môn học vì lớp đã có buổi học được tạo.'
                                    : ''
                                ),

                            CustomSelect::make('teacher_id')
                                ->label('Giáo viên')
                                ->required()
                                ->disabled(fn($livewire) => $livewire instanceof EditRecord)
                                ->helperText(fn($livewire) => $livewire instanceof EditRecord ?
                                    'Không thể chỉnh sửa giáo viên'
                                    : 'Chọn giáo viên phụ trách lớp.')
                                ->getOptionSelectService(TeacherService::class),

                            TextInput::make('max_students')
                                ->label('Sĩ số tối đa')
                                ->suffix("Học sinh")
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->helperText(function (?SchoolClass $record, $livewire) {
                                    if ($livewire instanceof EditRecord && $record) {
                                        $activeCount = $record->enrollments()->whereNull('left_at')->count();
                                        return "Hiện đang có {$activeCount} học sinh trong lớp.";
                                    }
                                    return null;
                                }),
                        ]),
                    ]),

                Section::make('Học phí & Lịch trình')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('base_fee_per_session')
                                ->label('Học phí cơ bản/buổi')
                                ->helperText("Học phí cơ bản/buổi là tiền mặc định mà học sinh phải trả mỗi khi tham gia buổi học.")
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->suffix('VND'),

                            DatePicker::make('start_at')
                                ->label('Ngày khai giảng')
                                ->required()
                                ->native(false)
                                ->disabled(fn($livewire) => $livewire instanceof EditRecord)
                                ->helperText(fn($livewire) => $livewire instanceof EditRecord ?
                                    'Bạn không thể chỉnh sửa ngày khai giảng.'
                                    : null)
                                ->displayFormat('d/m/Y'),

                            DatePicker::make('end_at')
                                ->label('Ngày kết thúc (Dự kiến)')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->afterOrEqual('start_at'),
                        ]),
                    ]),
            ]);
    }
}
