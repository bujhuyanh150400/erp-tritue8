<?php

namespace App\Filament\Resources\AttendanceSessions\Pages;

use App\Filament\Resources\AttendanceSessions\AttendanceSessionResource;
use Filament\Resources\Pages\EditRecord;

class EditAttendanceSession extends EditRecord
{
    protected static string $resource = AttendanceSessionResource::class;

    // Phải public
    public function getTitle(): string
    {
        return 'Chỉnh sửa điểm danh';
    }
}
