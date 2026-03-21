<?php


namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Resources\Staff\StaffResource;
use App\Services\StaffService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    protected StaffService $staffService;

    public function boot(StaffService $service): void
    {
        $this->staffService = $service;
    }

    public function getTitle(): string
    {
        return 'Tạo nhân viên';
    }

    /**
     * @throws Halt
     * @throws Throwable
     */
    protected function handleRecordCreation(array $data): Model
    {
        $result = $this->staffService->createStaff($data);

        if ($result->isError()) {
            Notification::make()
                ->danger()
                ->title('Không thể tạo nhân viên')
                ->body($result->getMessage())
                ->send();

            throw new Halt();
        }

        return $result->getData();
    }
}
