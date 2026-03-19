<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Student;
use App\Services\StudentService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;

    protected StudentService $studentService;

    public function boot(StudentService $service): void
    {
        $this->studentService = $service;
    }

    public function getTitle(): string
    {
        return 'Cập nhật hồ sơ học sinh';
    }

    protected function getHeaderActions(): array
    {
        return [
            CommonAction::deleteAction("Xóa học sinh")
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['user_name'] = $this->record->user?->username;
        return $data;
    }

    /**
     * @throws Halt
     * @throws \Throwable
     */
    protected function handleRecordUpdate(Model|Student $record, array $data): Model|Student
    {
        $result = $this->studentService->updateStudent($record, $data);
        if ($result->isError()) {
            Notification::make()
                ->danger()
                ->title('Lỗi cập nhật')
                ->body($result->getMessage())
                ->send();

            // Chặn tiến trình lưu, giữ Admin ở lại form với dữ liệu đang nhập dở
            throw new Halt();
        }

        return $result->getData();
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            CommonAction::backAction(self::getResource()),
        ];
    }
}
