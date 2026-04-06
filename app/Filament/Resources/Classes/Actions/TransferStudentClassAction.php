<?php

namespace App\Filament\Resources\Classes\Actions;

use App\Filament\Components\CustomSelect;
use App\Models\ClassEnrollment;
use App\Services\ClassService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

class TransferStudentClassAction
{
    public static function make(): Action
    {
        return Action::make('transfer_class')
            ->label('Chuyển lớp')
            ->icon('heroicon-m-arrows-right-left')
            ->color('info')
            ->schema([
                CustomSelect::make('new_class_id')
                    ->label('Chọn lớp mới')
                    ->serviceFilters(function (ClassEnrollment $record) {
                        return ['exclude_id' => $record->class_id];
                    })
                    ->getOptionSelectService(ClassService::class)
                    ->searchable()
                    ->required(),
                DatePicker::make('left_at')
                    ->label('Ngày chuyển lớp')
                    ->default(now())
                    ->required(),
                TextInput::make('fee_per_session')
                    ->label('Học phí lớp mới / buổi')
                    ->numeric()
                    ->placeholder('Để trống để dùng học phí gốc của lớp'),
                Textarea::make('note')
                    ->label('Ghi chú')
                    ->nullable(),
            ])
            ->action(function (ClassEnrollment $record, array $data, ClassService $classService) {
                $result = $classService->transferStudent($record->class_id, $record->student_id, $data);

                if ($result->isError()) {
                    Notification::make()
                        ->danger()
                        ->title('Lỗi chuyển lớp')
                        ->body($result->getMessage())
                        ->send();

                    throw new Halt();
                }

                Notification::make()
                    ->success()
                    ->title('Thành công')
                    ->body('Đã chuyển học sinh sang lớp mới.')
                    ->send();
            });
    }
}
