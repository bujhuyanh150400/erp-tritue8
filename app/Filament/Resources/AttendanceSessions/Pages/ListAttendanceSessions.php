<?php

namespace App\Filament\Resources\AttendanceSessions\Pages;

use App\Filament\Resources\AttendanceSessions\AttendanceSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceSessions extends ListRecords
{
    protected static string $resource = AttendanceSessionResource::class;

    public function getTitle(): string
    {
        return 'Danh sách các buổi học đã điểm danh';
    }
}
