<?php

namespace App\Filament\Resources\Classes\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\Classes\ClassResource;
use Filament\Resources\Pages\ListRecords;

class ListClasses extends ListRecords
{
    protected static string $resource = ClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CommonAction::createAction(),
        ];
    }
}
