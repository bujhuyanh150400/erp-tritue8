<?php

namespace App\Filament\Pages\ScheduleCalendar;

use App\Constants\GradeLevel;
use App\Constants\ScheduleStatus;
use App\Constants\ScheduleType;
use App\Filament\Components\CustomSelect;
use App\Services\RoomService;
use App\Services\SubjectService;
use App\Services\TeacherService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ScheduleCalendar extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.schedule-calendar';
    protected static string|null|\BackedEnum $navigationIcon = Heroicon::CalendarDays;

    protected static ?string $title = 'Lịch Tổng Hợp';

    public ?array $filters = [
        'active_classes_only' => true,
    ];

    public function mount()
    {
        $this->form->fill($this->filters);
    }

    /**
     * Định nghĩa form lọc lịch học
    *  @param Schema $schema
     * @return Schema
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make("Bộ lọc")
                    ->compact()
                    ->columns(4)
                    ->schema([
                        CustomSelect::make('teacher_id')
                            ->label('Giáo viên')
                            ->getOptionSelectService(TeacherService::class),
                        CustomSelect::make('subject_id')
                            ->label('Môn học')
                            ->getOptionSelectService(SubjectService::class),
                        CustomSelect::make('room_id')
                            ->label('Phòng học')
                            ->getOptionSelectService(RoomService::class),
                        Select::make('schedule_type')
                            ->label('Loại lịch')
                            ->searchable()
                            ->options(ScheduleType::options()),
                        Select::make('status')
                            ->label('Trạng thái')
                            ->searchable()
                            ->options(ScheduleStatus::options()),
                        Select::make('grade_level')
                            ->label('Khối lớp')
                            ->searchable()
                            ->options(GradeLevel::options()),
                        Toggle::make('active_classes_only')
                            ->label('Chỉ lớp Đang mở')
                            ->inline(false),

                        Actions::make([
                            Action::make('filter_button')
                                ->label('Lọc Lịch Học')
                                ->icon(Heroicon::Funnel)
                                ->action('applyFilters')
                        ])
                            ->columnSpanFull()
                            ->alignEnd()
                    ])
            ])
            ->statePath('filters');
    }

    /**
     * Hàm này sẽ tự động được gọi mỗi khi có bất kỳ ô input nào (có gắn ->live()) thay đổi giá trị.
     */
    public function applyFilters()
    {
        // Bắn sự kiện sang cho Widget Lịch hứng và lọc lại dữ liệu
        $this->dispatch('updateCalendarFilters', filters: $this->filters);
    }
}
