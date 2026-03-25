<?php

namespace App\Filament\Resources\Classes\Components;

use App\Constants\UserRole;
use App\Filament\Components\CustomSelect;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ClassService;
use App\Services\TeacherService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ChangeTeacherAction
{
    public static function make(): Action
    {
        return Action::make('change_teacher')
            ->label('Đổi giáo viên')
            ->icon(Heroicon::UserGroup)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Đổi giáo viên phụ trách')
            ->modalDescription('Lưu ý: Hệ thống sẽ kiểm tra lịch dạy của giáo viên mới trước khi thay đổi.')
            ->schema([
                CustomSelect::make('new_teacher_id')
                    ->label('Giáo viên mới')
                    ->required()
                    ->getOptionSelectService(TeacherService::class)
                    ->native(false)
            ])
            ->action(function (SchoolClass $record, array $data, ClassService $classService) {
                $result = $classService->changeTeacher($record, $data['new_teacher_id']);

                if ($result->isError()) {
                    Notification::make()
                        ->danger()
                        ->title('Thay đổi thất bại')
                        ->body($result->getMessage())
                        ->send();

                    // Hiển thị danh sách trùng lịch nếu có
                    if (!empty($result->getData())) {
                         // Có thể format hiển thị danh sách buổi trùng ở đây hoặc trong message
                    }
                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Thành công')
                    ->body('Đã đổi giáo viên phụ trách.')
                    ->send();
            });
    }
}
