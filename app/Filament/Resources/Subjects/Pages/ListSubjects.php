<?php

namespace App\Filament\Resources\Subjects\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\Subjects\SubjectResource;
use Filament\Resources\Pages\ListRecords;

class ListSubjects extends ListRecords
{
    protected static string $resource = SubjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CommonAction::createAction("Tạo môn học"),
        ];
    }
}
