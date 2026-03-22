<?php

namespace App\Filament\Resources\Subjects\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Services\SubjectService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;

class CreateSubject extends CreateRecord
{
    protected SubjectService $subjectService;

    protected static string $resource = SubjectResource::class;

    public function boot(SubjectService $service): void
    {
        $this->subjectService = $service;
    }

    public function getTitle(): string
    {
        return 'Tạo môn học';
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $result = $this->subjectService->createSubject($data);
        if ($result->isError()) {
            // Hiển thị thông báo Toast đỏ góc màn hình
            Notification::make()
                ->danger()
                ->title('Không thể tạo môn học')
                ->body($result->getMessage())
                ->send();
            throw new Halt();
        }
        return $result->getData();
    }

    protected function getFormActions(): array
    {
        return [
            CommonAction::backAction(self::getResource()),
            $this->getCreateFormAction(),
        ];
    }
}
