<?php

namespace App\Filament\Resources\Classes\Components;

use App\Models\ClassEnrollment;
use App\Services\ClassService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class EditStudentFeeAction
{
    public static function make(): Action
    {
        return Action::make('edit_fee')
            ->label('Sửa học phí')
            ->icon(Heroicon::CurrencyDollar)
            ->color('warning')
            ->schema([
                TextEntry::make('history')
                    ->hiddenLabel()
                    ->state(fn (ClassEnrollment $record) => new HtmlString(
                        Blade::render(
                            '@livewire("' . FeeHistoryTable::class . '", ["classId" => $classId, "studentId" => $studentId, "baseFee" => $baseFee])',
                            [
                                'classId' => $record->class_id,
                                'studentId' => $record->student_id,
                                'baseFee' => $record->base_fee_per_session
                            ]
                        )
                    ))
                    ->columnSpanFull(),
                TextInput::make('fee_per_session')
                    ->label('Học phí mới / buổi')
                    ->numeric()
                    ->required(),
                DatePicker::make('fee_effective_from')
                    ->label('Áp dụng từ ngày')
                    ->default(now())
                    ->required(),
            ])
            ->action(function (ClassEnrollment $record, array $data, ClassService $classService) {
                $result = $classService->updateStudentFee($record->class_id, $record->student_id, $data);

                if ($result->isError()) {
                    Notification::make()
                        ->danger()
                        ->title('Lỗi thiết lập học phí')
                        ->body($result->getMessage())
                        ->send();

                    throw new Halt();
                }

                Notification::make()
                    ->success()
                    ->title('Thành công')
                    ->body('Đã thiết lập học phí mới cho học sinh.')
                    ->send();
            });
    }
}
