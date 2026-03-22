<?php

namespace App\Filament\Resources\Subjects\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Models\Subject;
use App\Services\SubjectService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditSubject extends EditRecord
{
    protected SubjectService $subjectService;

    protected static string $resource = SubjectResource::class;

    public function boot(SubjectService $service): void
    {
        $this->subjectService = $service;
    }

    public function getTitle(): string
    {
        return 'Cập nhật hồ sơ học sinh';
    }

    protected function getHeaderActions(): array
    {
        return [
            CommonAction::deleteAction("Xóa môn học")
                ->using(function (Subject $record, SubjectService $subjectService) {
                    $result = $subjectService->deleteSubject($record);
                    if ($result->isError()) {
                        // Bắn thông báo lỗi
                        Notification::make()
                            ->danger()
                            ->title('Lỗi thao tác')
                            ->body($result->getMessage())
                            ->send();
                        throw new Halt();
                    }
                    return $record;
                })
                ->successNotificationTitle('Đã xóa môn học thành công'),
        ];
    }

    /**
     * @throws Halt|\Throwable
     */
    protected function handleRecordUpdate(Model|Subject $record, array $data): Model|Subject
    {
        $result = $this->subjectService->updateSubject($record, $data);
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

    protected function getFormActions(): array
    {
        return [
            CommonAction::backAction(self::getResource()),
            $this->getSaveFormAction(),
        ];
    }
}
