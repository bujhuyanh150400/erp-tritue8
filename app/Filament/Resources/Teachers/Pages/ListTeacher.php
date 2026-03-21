<?php


namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\Teachers\TeacherResource;
use Filament\Resources\Pages\ListRecords;

class ListTeacher extends ListRecords
{
    protected static string $resource = TeacherResource::class;

    public function getTitle(): string
    {
        return 'Danh sách giáo viên';
    }
    protected function getHeaderActions(): array
    {
        return [
            CommonAction::createAction(),
        ];
    }
}
