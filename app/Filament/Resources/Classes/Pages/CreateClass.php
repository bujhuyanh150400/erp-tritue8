<?php

namespace App\Filament\Resources\Classes\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\Classes\ClassResource;
use App\Models\SchoolClass;
use App\Services\ClassService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateClass extends CreateRecord
{
    protected ClassService $classService;

    protected static string $resource = ClassResource::class;

    public function boot(ClassService $service): void
    {
        $this->classService = $service;
    }

    public function getTitle(): string
    {
        return 'Tạo lớp học';
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            CommonAction::backAction(self::getResource()),
        ];
    }

    protected function handleRecordCreation(array $data): Model|SchoolClass
    {
        $result = $this->classService->createClass($data);
        if ($result->isError()) {
            // Hiển thị thông báo Toast đỏ góc màn hình
            Notification::make()
                ->danger()
                ->title('Không thể tạo lớp học')
                ->body($result->getMessage())
                ->send();
            throw new Halt();
        }
        return $result->getData();
    }
}
