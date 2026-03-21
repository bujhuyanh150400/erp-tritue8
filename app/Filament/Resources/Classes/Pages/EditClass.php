<?php

namespace App\Filament\Resources\Classes\Pages;

use App\Filament\Components\CommonAction;
use App\Filament\Resources\Classes\ClassResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditClass extends EditRecord
{
    protected static string $resource = ClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            CommonAction::backAction(self::getResource()),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Validation for max_students
        if (isset($data['max_students'])) {
            $activeStudents = $this->record->activeEnrollments()->count();
            if ($data['max_students'] < $activeStudents) {
                Notification::make()
                    ->danger()
                    ->title('Lỗi cập nhật')
                    ->body("Sĩ số tối đa không thể nhỏ hơn số học sinh hiện tại ({$activeStudents}).")
                    ->send();
                $this->halt();
            }
        }

        return $data;
    }
}
