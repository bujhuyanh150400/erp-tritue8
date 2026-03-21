<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\Teachers\TeacherResource;
use Filament\Resources\Pages\ViewRecord;

class ViewTeacher extends ViewRecord
{
    protected static string $resource = TeacherResource::class;

    public function getTitle(): string
    {
        return 'Hồ sơ giáo viên';
    }
    protected function getHeaderActions(): array
    {
        return [
            CommonAction::editAction(),
            CommonAction::backAction(self::getResource()),
        ];
    }
}

