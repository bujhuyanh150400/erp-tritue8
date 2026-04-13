<?php

namespace App\Filament\Resources\Classes\Schemas;

use App\Constants\ClassStatus;
use App\Constants\GradeLevel;
use App\Filament\Resources\Classes\Components\ClassScheduleHistoryTable;
use App\Filament\Resources\Classes\Components\ClassStudentListTable;
use App\Filament\Resources\Classes\Components\ClassTemplateScheduleTable;
use App\Filament\Resources\Teachers\TeacherResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class ClassInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Tabs')
                    ->columnSpanFull()
                    ->contained(false)
                    ->persistTabInQueryString()
                    ->tabs([
                        //  Thông tin lớp
                        Tab::make('Thông tin lớp')
                            ->icon(Heroicon::InformationCircle)
                            ->iconPosition(IconPosition::Before)
                            ->columns(3)
                            ->schema([
                                // Cột 1: Thông tin cơ bản
                                Section::make('Thông tin cơ bản')
                                    ->icon(Heroicon::InformationCircle)
                                    ->compact()
                                    ->columnSpan(1)
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label('Tên lớp')
                                            ->size(TextSize::Large)
                                            ->weight(FontWeight::Bold)
                                            ->color('primary'),
                                        TextEntry::make('code')
                                            ->label('Mã lớp')
                                            ->size(TextSize::Large)
                                            ->weight(FontWeight::Bold)
                                            ->color('primary')
                                            ->copyable()
                                            ->copyMessage('Đã copy mã lớp!'),
                                        TextEntry::make('subject.name')
                                            ->label('Môn học')
                                            ->icon('heroicon-m-book-open'),
                                        TextEntry::make('grade_level')
                                            ->label('Khối')
                                            ->badge(),
                                    ]),

                                // Cột 2: Nhân sự & Sĩ số
                                Section::make('Nhân sự & Sĩ số')
                                    ->icon(Heroicon::Users)
                                    ->compact()
                                    ->columnSpan(1)
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('teacher.full_name') // Tự động JOIN sang bảng teachers
                                        ->label('Giáo viên phụ trách')
                                            ->icon('heroicon-m-academic-cap')
                                            ->url(fn($record) => $record->teacher_id ? TeacherResource::getUrl('view', ['record' => $record->teacher_id]) : null)
                                            ->color('info'),
                                        TextEntry::make('max_students')
                                            ->label('Sĩ số tối đa')
                                            ->suffix(' học sinh'),
                                        TextEntry::make('status')
                                            ->label('Trạng thái')
                                            ->badge()
                                            ->formatStateUsing(fn(ClassStatus $state): string => $state->label())
                                            ->color(fn(ClassStatus $state): string => match ($state) {
                                                ClassStatus::Active => 'success',
                                                ClassStatus::Suspended => 'warning',
                                                ClassStatus::Ended => 'danger',
                                                default => 'gray',
                                            }),
                                    ]),

                                // Cột 3: Tài chính & Thời gian
                                Section::make('Tài chính & Thời gian')
                                    ->icon(Heroicon::CurrencyDollar)
                                    ->compact()
                                    ->columnSpan(1)
                                    ->schema([
                                        TextEntry::make('base_fee_per_session')
                                            ->label('Học phí cơ bản / buổi')
                                            ->money('VND')
                                            ->badge()
                                            ->color('success'),
                                        TextEntry::make('teacher_salary_per_session')
                                            ->label('Lương GV cơ bản / buổi')
                                            ->money('VND')
                                            ->badge()
                                            ->color('warning'),
                                        Grid::make(2)->schema([
                                            TextEntry::make('start_at')
                                                ->label('Khai giảng')
                                                ->date('d/m/Y')
                                                ->default(null)
                                                ->placeholder('Chưa xác định'),
                                            TextEntry::make('end_at')
                                                ->label('Kết thúc lớp')
                                                ->date('d/m/Y')
                                                ->default(null)
                                                ->placeholder('Chưa xác định'),
                                        ]),
                                    ]),
                            ]),
                        // Danh sách học sinh
                        Tab::make('Danh sách học sinh')
                            ->icon(Heroicon::UserGroup)
                            ->iconPosition(IconPosition::Before)
                            ->schema([
                                Livewire::make(ClassStudentListTable::class)->lazy()
                            ]),
                        Tab::make('Lịch học cố định')
                            ->icon(Heroicon::CalendarDateRange)
                            ->iconPosition(IconPosition::Before)
                            ->schema([
                                Livewire::make(ClassTemplateScheduleTable::class)->lazy()
                            ]),
                        // Lịch sử buổi học
                        Tab::make('Lịch sử buổi học')
                            ->icon(Heroicon::Calendar)
                            ->iconPosition(IconPosition::Before)
                            ->schema([
                                Livewire::make(ClassScheduleHistoryTable::class)->lazy()
                            ]),
                    ])
            ]);
    }
}
