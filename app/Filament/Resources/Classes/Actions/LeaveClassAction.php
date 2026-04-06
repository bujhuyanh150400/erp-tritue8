<?php

namespace App\Filament\Resources\Classes\Actions;

use App\Models\ClassEnrollment;
use App\Services\ClassService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

class LeaveClassAction
{
    public static function make(): Action
    {
        return Action::make('leave_class')
            ->label('Cho nghỉ')
            ->icon('heroicon-m-arrow-right-on-rectangle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Chốt sổ thôi học')
            ->modalDescription('Học sinh sẽ rời lớp nhưng dữ liệu điểm danh cũ vẫn được giữ nguyên.')
            ->schema([
                DatePicker::make('left_at')
                    ->label('Ngày chính thức nghỉ')
                    ->default(now())
                    ->required(),
                Textarea::make('note')
                    ->label('Lý do nghỉ')
                    ->required(),
            ])
            ->action(function (ClassEnrollment $record, array $data, ClassService $classService) {
                $result = $classService->removeStudent($record->class_id, $record->student_id, $data);

                if ($result->isError()) {
                    Notification::make()
                        ->danger()
                        ->title('Lỗi khi chốt thôi học')
                        ->body($result->getMessage())
                        ->send();

                    throw new Halt();
                }

                Notification::make()
                    ->success()
                    ->title('Thành công')
                    ->body('Đã chốt sổ thôi học cho học sinh.')
                    ->send();
            });
    }
}
