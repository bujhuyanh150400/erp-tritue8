<?php

namespace App\Filament\Resources\Classes\Actions;

use App\Constants\FeeType;
use App\Constants\ScheduleStatus;
use App\Filament\Components\CustomSelect;
use App\Models\ScheduleInstance;
use App\Services\RoomService;
use App\Services\ScheduleService;
use App\Services\TeacherService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

class CreateMakeupSessionAction
{
    public static function make(): Action
    {
        return Action::make('create_makeup')
            ->label('Tạo buổi bù')
            ->icon(Heroicon::PlusCircle)
            ->color('info')
            ->visible(fn (ScheduleInstance $record) => $record->status === ScheduleStatus::Cancelled || auth()->user()->is_admin)
            ->modalHeading('Tạo buổi học bù')
            ->form(fn (ScheduleInstance $record) => [
                Placeholder::make('original_info')
                    ->label('Thông tin buổi gốc')
                    ->content("Ngày: {$record->date->format('d/m/Y')}, Lớp: {$record->class->name}, GV: {$record->teacher->full_name}")
                    ->columnSpanFull(),

                DatePicker::make('date')
                    ->label('Ngày học bù')
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(now()),

                Grid::make(2)->schema([
                    TextInput::make('start_time')
                        ->label('Giờ bắt đầu')
                        ->type('time')
                        ->required()
                        ->default($record->start_time),
                    TextInput::make('end_time')
                        ->label('Giờ kết thúc')
                        ->type('time')
                        ->required()
                        ->default($record->end_time),
                ]),

                CustomSelect::make('room_id')
                    ->label('Phòng học')
                    ->required()
                    ->getOptionSelectService(RoomService::class)
                    ->default($record->room_id),

                CustomSelect::make('teacher_id')
                    ->label('Giáo viên')
                    ->required()
                    ->getOptionSelectService(TeacherService::class)
                    ->default($record->teacher_id),

                Select::make('fee_type')
                    ->label('Hình thức tính phí')
                    ->options(FeeType::options())
                    ->required()
                    ->default(FeeType::Normal->value)
                    ->live(),

                TextInput::make('custom_fee_per_session')
                    ->label('Học phí tùy chỉnh')
                    ->numeric()
                    ->required()
                    ->visible(fn (Get $get) => $get('fee_type') == FeeType::Custom->value),

                TextInput::make('custom_salary')
                    ->label('Lương GV buổi bù (Tùy chọn)')
                    ->numeric()
                    ->helperText('Nếu để trống sẽ tự lấy cấu hình lương của lớp.'),
            ])
            ->action(function (ScheduleInstance $record, array $data, ScheduleService $scheduleService) {
                $result = $scheduleService->createMakeupSession($record, $data);

                if ($result->isError()) {
                    Notification::make()
                        ->danger()
                        ->title('Lỗi')
                        ->body($result->getMessage())
                        ->send();
                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Thành công')
                    ->body($result->getData() ?? 'Tạo buổi bù thành công')
                    ->send();
            });
    }
}
