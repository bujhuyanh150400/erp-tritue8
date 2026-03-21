<?php


namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\Teachers\TeacherResource;
use App\Services\TeacherService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Throwable;

class CreateTeacher extends CreateRecord
{
    protected static string $resource = TeacherResource::class;

    protected TeacherService $teacherService;

    public function boot(TeacherService $service): void
    {
        $this->teacherService = $service;
    }

    public function getTitle(): string
    {
        return 'Tạo hồ sơ giáo viên';
    }

    /**
     * @throws Halt
     * @throws Throwable
     */
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $result = $this->teacherService->createTeacher($data);

        if ($result->isError()) {
            Notification::make()
                ->danger()
                ->title('Không thể tạo giáo viên')
                ->body($result->getMessage())
                ->send();

            throw new Halt();
        }

        return $result->getData();
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            CommonAction::backAction(self::getResource()),
        ];
    }
}
