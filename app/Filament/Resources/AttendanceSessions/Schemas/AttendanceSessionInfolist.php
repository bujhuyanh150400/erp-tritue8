<?php

namespace App\Filament\Resources\AttendanceSessions\Schemas;

use App\Constants\AttendanceSessionStatus;
use App\Constants\ScheduleType;
use App\Filament\Components\AppRichEditor;
use App\Filament\Resources\AttendanceSessions\Components\AttendanceStudentTable;
use App\Services\AttendanceService;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class AttendanceSessionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make()
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->contained(false)
                    ->tabs([
                        Tab::make('Thông tin buổi học')
                            ->icon(Heroicon::DocumentText)
                            ->iconPosition(IconPosition::Before)
                            ->schema([
                                // --- THÔNG TIN BUỔI HỌC ---
                                Section::make('')
                                    ->compact()
                                    ->schema([
                                        // --- PHẦN 1: THÔNG TIN LỚP & NHÂN SỰ ---
                                        Grid::make(4)->schema([
                                            TextEntry::make('class.name')
                                                ->label('Lớp học (Môn)')
                                                ->weight(FontWeight::Bold)
                                                ->color('primary')
                                                ->size(TextSize::Large)
                                                // Ghép Tên Lớp và Tên Môn học
                                                ->formatStateUsing(fn($record) => $record->class->name . ' (' . ($record->class->subject->name ?? 'N/A') . ')'),

                                            TextEntry::make('teacher.full_name')
                                                ->label('Giáo viên phụ trách')
                                                ->weight(FontWeight::Bold)
                                                ->size(TextSize::Large)
                                                ->icon(Heroicon::UserCircle),

                                            TextEntry::make('scheduleInstance.room.name')
                                                ->label('Tên Phòng học')
                                                ->icon(Heroicon::RectangleStack)
                                                ->size(TextSize::Large)
                                                ->weight(FontWeight::Bold)
                                                ->default('Chưa xếp phòng'),
                                        ]),

                                        // --- PHẦN 2: THỜI GIAN & TRẠNG THÁI ---
                                        Grid::make(4)->schema([
                                            TextEntry::make('session_date')
                                                ->label('Ngày học')
                                                ->date('d/m/Y')
                                                ->icon('heroicon-m-calendar-days'),

                                            TextEntry::make('time')
                                                ->label('Khung giờ học')
                                                ->icon('heroicon-m-clock')
                                                // Ghép Start Time và End Time
                                                ->getStateUsing(fn($record) => Carbon::parse($record->scheduleInstance->start_time)->format('H:i') . ' - ' .
                                                    Carbon::parse($record->scheduleInstance->end_time)->format('H:i')
                                                ),

                                            TextEntry::make('scheduleInstance.schedule_type')
                                                ->label('Loại lịch')
                                                ->badge()
                                                ->color('info')
                                                ->color(fn(ScheduleType $state) => $state->colorFilament())
                                                ->formatStateUsing(fn(ScheduleType $state) => $state->label ?? $state),

                                            TextEntry::make('status')
                                                ->label('Trạng thái')
                                                ->badge()
                                                ->color(fn(AttendanceSessionStatus $state) => $state->colorFilament()),
                                        ]),


                                    ]),
                                // --- NỘI DUNG BÀI GIẢNG ---
                                Section::make()
                                    ->compact()
                                    ->schema([
                                        self::getTextEntryContentAtLessonContent('lesson_content', 'Nội dung bài giảng'),

                                        self::getTextEntryContentAtLessonContent('homework', 'Nội dung bài tập'),

                                        self::getTextEntryContentAtLessonContent('next_session_note', 'Nội dung buổi sau'),

                                        self::getTextEntryContentAtLessonContent('general_note', 'Ghi chú'),
                                    ]),
                            ]),
                        Tab::make('Danh sách điểm danh')
                            ->icon(Heroicon::UserGroup)
                            ->iconPosition(IconPosition::Before)
                            ->schema([
                                Livewire::make(AttendanceStudentTable::class)->lazy()
                            ]),
                    ])
            ]);
    }

    protected static function getTextEntryContentAtLessonContent(string $make, string $label): TextEntry
    {
        return TextEntry::make($make)
            ->label($label)
            ->placeholder('Chưa có nội dung')
            ->html()
            ->prose()
            ->columnSpanFull()
            ->extraAttributes(['class' => 'bg-gray-50 dark:bg-gray-900 p-3 rounded-lg border border-gray-100 dark:border-gray-800'])
            ->hintAction(
                Action::make("edit_action_{$make}")
                    ->label('Sửa')
                    ->icon(Heroicon::PencilSquare)
                    ->color('primary')
                    ->modalHeading("Chỉnh sửa Nội dung $label")
                    ->modalWidth(Width::Full)
                    ->schema([
                        AppRichEditor::make($make)
                            ->hiddenLabel()
                            ->required(),
                    ])
                    ->fillForm(fn($record) => [
                        $make => $record->{$make} ?? '',
                    ])
                    // Xử lý lưu dữ liệu
                    ->action(function (array $data, $record, AttendanceService $attendanceService) {
                        $result = $attendanceService->updateSessionInfo($record, $data);
                        if ($result->isSuccess()) {
                            Notification::make()
                                ->title('Đã lưu nội dung!')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Lỗi lưu nội dung!')
                                ->body($result->getMessage())
                                ->send();
                            throw new Halt();
                        }
                    }),
            );
    }
}
