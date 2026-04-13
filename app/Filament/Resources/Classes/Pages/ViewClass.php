<?php

namespace App\Filament\Resources\Classes\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\Classes\Actions\ChangeClassStatusAction;
use App\Filament\Resources\Classes\ClassResource;
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
            // Edit action
            CommonAction::editAction(),
            // Đổi trạng thái lớp
            ChangeClassStatusAction::make(),
        ];
    }
}
