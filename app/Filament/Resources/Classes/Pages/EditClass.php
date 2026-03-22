<?php

namespace App\Filament\Resources\Classes\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\Classes\ClassResource;
use App\Models\SchoolClass;
use App\Services\ClassService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditClass extends EditRecord
{
    protected ClassService $classService;

    protected static string $resource = ClassResource::class;
    public function boot(ClassService $service): void
    {
        $this->classService = $service;
    }

    public function getTitle(): string
    {
        return 'Cập nhật lớp học';
    }
    protected function getHeaderActions(): array
    {
        return [

        ];
    }

    protected function getFormActions(): array
    {
        return [
            CommonAction::backAction(self::getResource()),
            $this->getSaveFormAction(),
        ];
    }

    protected function handleRecordUpdate(Model|SchoolClass $record, array $data): Model|SchoolClass
    {
        $result = $this->classService->updateClass($record, $data);
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
