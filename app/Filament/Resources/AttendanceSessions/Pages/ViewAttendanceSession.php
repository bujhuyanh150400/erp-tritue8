<?php

namespace App\Filament\Resources\AttendanceSessions\Pages;

use App\Constants\AttendanceSessionStatus;
use App\Filament\Components\CommonAction;
use App\Filament\Resources\AttendanceSessions\AttendanceSessionResource;
use App\Helpers\Helper;
use App\Services\AttendanceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;

class ViewAttendanceSession extends ViewRecord
{
    protected static string $resource = AttendanceSessionResource::class;
    public function getTitle(): string
    {
        return 'Chi tiết điểm danh';
    }

    protected function getHeaderActions(): array
    {
        return [
            CommonAction::backAction(self::getResource()),
            Action::make('complete_session')
                ->label('Chốt sổ & Hoàn thành')
                ->icon(Heroicon::CheckBadge)
                ->color('success')
                // UX: Bắt buộc xác nhận để tránh ấn nhầm
                ->requiresConfirmation()
                ->modalHeading('Xác nhận chốt sổ buổi học')
                ->modalDescription('Bạn có chắc chắn muốn chốt sổ? Sau khi chốt, toàn bộ dữ liệu điểm danh, điểm số sẽ được ghi nhận và (dự kiến) thông báo sẽ được gửi tới phụ huynh. Vui lòng kiểm tra kỹ dữ liệu.')
                ->modalSubmitActionLabel('Đồng ý, Chốt sổ')
                // Chỉ hiện nút này nếu buổi học CHƯA hoàn thành
                ->visible(fn () => $this->record->status !== AttendanceSessionStatus::Completed)
                ->action(function (AttendanceService $service) {
                    $result = $service->completeSession(
                        sessionId: $this->record->id,
                    );
                    if ($result->isSuccess()) {
                        Notification::make()
                            ->title('Đã chốt sổ buổi học thành công')
                            ->success()
                            ->send();
                        return Helper::refreshPage();
                    } else {
                        Notification::make()
                            ->title('Không thể chốt sổ')
                            ->body($result->getMessage())
                            ->danger()
                            ->send();
                        throw new Halt();
                    }
                }),

            Action::make('reopen_session')
                ->label('Mở lại buổi đã hoàn thành')
                ->icon(Heroicon::ArrowUturnLeft)
                ->color('danger')
                ->requiresConfirmation()
                ->modalIcon(Heroicon::ExclamationTriangle)
                ->modalHeading('Xác nhận mở chốt sổ buổi học')
                ->modalDescription('Việc mở chốt sổ sẽ cho phép thay đổi dữ liệu điểm danh và điểm số, có thể ảnh hưởng đến học phí đã tính. Bạn có chắc chắn?')
                ->modalSubmitActionLabel('Đồng ý, Mở chốt')
                ->authorize(fn () => auth()->user()?->isAdmin() ?? false)
                ->visible(fn () => (auth()->user()?->isAdmin() ?? false) && $this->record->status === AttendanceSessionStatus::Completed)
                ->action(function (AttendanceService $service) {
                    $result = $service->reopenCompletedSession(
                        sessionId: $this->record->id,
                    );

                    if ($result->isSuccess()) {
                        Notification::make()
                            ->title('Đã mở lại buổi điểm danh')
                            ->success()
                            ->send();
                        return Helper::refreshPage();
                    } else {
                        Notification::make()
                            ->title('Không thể mở lại buổi điểm danh')
                            ->body($result->getMessage())
                            ->danger()
                            ->send();
                        throw new Halt();
                    }
                }),
        ];
    }
}
