<?php

namespace App\Filament\Resources\Classes\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\Classes\ClassResource;
use App\Filament\Resources\Classes\Components\ChangeClassStatusAction;
use App\Filament\Resources\Classes\Components\ChangeTeacherAction;
use App\Filament\Resources\Classes\Components\CreateScheduleTemplateAction;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;

class ViewClass extends ViewRecord
{
    protected static string $resource = ClassResource::class;

    protected static ?string $title = 'Trang chi tiết lớp';
    protected function getHeaderActions(): array
    {
        return [
            // Back action
            CommonAction::backAction(self::getResource()),
            // Action group
            ActionGroup::make([
                // Edit action
                CommonAction::editAction(),
                // Đổi giáo viên
                ChangeTeacherAction::make(),
                // Đổi trạng thái lớp
                ChangeClassStatusAction::make(),
                // Thêm lịch cố định
                CreateScheduleTemplateAction::make(),
            ]),

        ];
    }
}
