<?php

namespace App\Filament\Resources\Classes\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\Classes\ClassResource;
use Filament\Resources\Pages\CreateRecord;

class CreateClass extends CreateRecord
{
    protected static string $resource = ClassResource::class;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            CommonAction::backAction(self::getResource()),
        ];
    }
}
