<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\Students\StudentResource;
use App\Services\StudentService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Throwable;

class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;

    protected StudentService $studentService;

    public function boot(StudentService $service): void
    {
        $this->studentService = $service;
    }

    public function getTitle(): string
    {
        return 'Tạo hồ sơ học sinh';
    }
    /**
     * @throws Halt
     * @throws Throwable
     */
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $result = $this->studentService->createStudent($data);
        if ($result->isError()) {
            // Hiển thị thông báo Toast đỏ góc màn hình
            Notification::make()
                ->danger()
                ->title('Không thể tạo học sinh')
                ->body($result->getMessage())
                ->send();

            // Ném exception Halt của Filament.
            // Nó sẽ báo cho Filament biết là quá trình bị gián đoạn,
            // không được redirect, giữ nguyên các thông tin Admin vừa nhập trên Form.
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
