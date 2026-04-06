<?php

namespace App\Filament\Resources\RewardItemResource\Pages;

use App\Filament\Resources\RewardItemResource\RewardItemResource;
use App\Services\RewardItemService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateRewardItem extends CreateRecord
{
    protected static string $resource = RewardItemResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $service = app(RewardItemService::class);
        $result = $service->createRewardItem($data);

        if ($result->isSuccess()) {
            Notification::make()
                ->body($result->getMessage())
                ->success()
                ->send();
            return $result->getData();
        } else {
            Notification::make()
                ->title('Lỗi')
                ->body($result->getMessage())
                ->danger()
                ->send();
            $this->halt();
        }
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}
