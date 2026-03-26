<?php

namespace App\Filament\Resources\AttendanceSessions\Pages;

use App\Filament\Resources\AttendanceSessions\AttendanceSessionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAttendanceSession extends ViewRecord
{
    protected static string $resource = AttendanceSessionResource::class;

    public function getTitle(): string
    {
        return 'Chi tiết điểm danh';
    }
}
