<?php

namespace App\Filament\Resources\RewardItemResource\Pages;

use App\Filament\Resources\RewardItemResource\RewardItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRewardItems extends ListRecords
{
    protected static string $resource = RewardItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }


}
