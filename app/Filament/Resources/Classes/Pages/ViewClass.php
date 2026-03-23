<?php

namespace App\Filament\Resources\Classes\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\Classes\ClassResource;
use App\Filament\Resources\Classes\Components\AddStudentToClassAction;
use Filament\Resources\Pages\ViewRecord;

class ViewClass extends ViewRecord
{
    protected static string $resource = ClassResource::class;

    protected static ?string $title = 'Trang chi tiết lớp';
    protected function getHeaderActions(): array
    {
        return [
            CommonAction::backAction(self::getResource()),
            CommonAction::editAction(),
        ];
    }
}
