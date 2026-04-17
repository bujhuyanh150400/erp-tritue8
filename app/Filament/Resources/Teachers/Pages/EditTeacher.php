<?php


namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\Teachers\TeacherResource;
use App\Models\Teacher;
use App\Services\TeacherService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditTeacher extends EditRecord
{
    protected static string $resource = TeacherResource::class;

    protected TeacherService $teacherService;

    public function boot(TeacherService $service): void
    {
        $this->teacherService = $service;
    }

    public function getTitle(): string
    {
        return 'Cập nhật hồ sơ giáo viên';
    }

    protected function getHeaderActions(): array
    {
        return [
            CommonAction::deleteAction("Xóa giáo viên")
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $salaryConfig = $this->record->salaryConfig;
        $user = $this->record->user;
        $data['user_name'] = $user?->username;
        $data['salary_per_session'] = $salaryConfig?->salary_per_session;
        $data['salary_type'] = $salaryConfig?->salary_type;

        return $data;
    }

    /**
     * @throws Halt
     * @throws \Throwable
     */
    protected function handleRecordUpdate(Model|Teacher $record, array $data): Model|Teacher
    {
        $result = $this->teacherService->updateTeacher($record, $data);

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
            $this->getSaveFormAction(),
            CommonAction::backAction(self::getResource()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Cập nhật thành công';
    }
}
