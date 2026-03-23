<?php

namespace App\Filament\Resources\Classes\Components;

use App\Constants\ClassStatus;
use App\Models\SchoolClass;
use App\Services\ClassService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class ChangeClassStatusAction
{
    public static function make(): Action
    {
        return Action::make('change_status')
            ->label('Đổi trạng thái')
            ->icon(Heroicon::ArrowPath)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Cập nhật trạng thái lớp học')
            ->form([
                Select::make('status')
                    ->label('Trạng thái mới')
                    ->options(ClassStatus::options())
                    ->required()
                    ->native(false)
            ])
            ->action(function (SchoolClass $record, array $data, ClassService $classService) {
                $result = $classService->changeStatusClass($record, ClassStatus::from($data['status']));

                if ($result->isError()) {
                    Notification::make()
                        ->danger()
                        ->title('Lỗi cập nhật')
                        ->body($result->getMessage())
                        ->send();
                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Thành công')
                    ->body('Trạng thái lớp đã được cập nhật.')
                    ->send();
            });
    }
}
