<?php

namespace App\Filament\Resources\RewardItemResource\Pages;

use App\Filament\Resources\RewardItemResource\RewardItemResource;
use App\Services\RewardItemService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditRewardItem extends EditRecord
{
    protected static string $resource = RewardItemResource::class;

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        $service = app(RewardItemService::class);
        $result = $service->updateRewardItem($record->id, $data);

        if ($result->isSuccess()) {
            Notification::make()
                ->title('Thành công')
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

    protected function afterSave(): void
    {
        //  reload Livewire
        $this->redirect($this->getResource()::getUrl('index'), navigate: true);
    }
}
