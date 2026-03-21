<?php


namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Resources\Staff\StaffResource;
use App\Models\Staff;
use App\Services\StaffService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditStaff extends EditRecord
{
    protected static string $resource = StaffResource::class;

    protected StaffService $staffService;

    public function boot(StaffService $service): void
    {
        $this->staffService = $service;
    }

    public function getTitle(): string
    {
        return 'Cập nhật nhân viên';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['user_name'] = $this->record->user?->username;
        return $data;
    }

    protected function handleRecordUpdate(Model|Staff $record, array $data): Model|Staff
    {
        $result = $this->staffService->updateStaff($record, $data);

        if ($result->isError()) {
            Notification::make()
                ->danger()
                ->title('Lỗi cập nhật')
                ->body($result->getMessage())
                ->send();

            throw new Halt();
        }

        return $result->getData();
    }
}
