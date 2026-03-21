<?php

namespace App\Filament\Resources\Rooms\Pages;

use App\Constants\RoomStatus;
use App\Filament\Components\CommonAction;
use App\Filament\Resources\Rooms\RoomResource;
use App\Models\ClassScheduleTemplate;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditRoom extends EditRecord
{
    protected static string $resource = RoomResource::class;

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
        // Example check: Capacity reduction
        if (isset($data['capacity']) && $data['capacity'] < $this->record->capacity) {
            // Perform check for existing classes and capacity
            // This is a simplified check, implement full logic as per requirement
             $existingClassesCount = ClassScheduleTemplate::where('room_id', $this->record->id)
                 ->where(function ($query) {
                     $query->whereNull('end_date')->orWhere('end_date', '>=', now());
                 })
                 ->withCount(['class.enrollments' => function($q) {
                     $q->whereNull('left_at');
                 }])
                 ->get();

             foreach($existingClassesCount as $template) {
                 if ($template->class && $template->class->enrollments_count > $data['capacity']) {
                     Notification::make()
                        ->danger()
                        ->title('Không thể giảm sức chứa')
                        ->body("Phòng đang có lớp {$template->class->name} với {$template->class->enrollments_count} học sinh, không thể giảm xuống {$data['capacity']} chỗ")
                        ->send();
                     $this->halt();
                 }
             }
        }

        return $data;
    }
}
